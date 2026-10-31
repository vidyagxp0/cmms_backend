<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveMaintenancePlannerReportService
{
    /*
     * equipment (records) printed in the planner grid:
     * - first page shares the page with General Information, so it holds fewer rows
     * - every following page holds at most EQUIPMENT_PER_PAGE rows, all months are repeated on each page
     */
    private const FIRST_PAGE_EQUIPMENT = 20;
    private const EQUIPMENT_PER_PAGE = 10;

    /* shown in the header row when process_data has no documentNumber / documentType */
    private const DEFAULT_DOCUMENT_NUMBER = '-';
    private const DEFAULT_DOCUMENT_TYPE = '-';

    /* generate preventive maintenance planner report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
                'gridRecords',
            ])->findOrFail($id);

            /* process data */
            $processFields = [];
            foreach (self::decodeArray($record->process_data) as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? null;
                if (!$key) {
                    continue;
                }

                $processFields[$key] = [
                    'key' => $key,
                    'label' => $field['label'] ?? self::formatFieldLabel($key),
                    'value' => $field['value'] ?? null,
                ];
            }

            /* map record attachments to the attachment fields */
            $recordAttachments = self::decodeArray($record->attachments);
            foreach ($processFields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachmentsByField($recordAttachments, $key);
                }
            }
            unset($field);

            /* header values */
            $documentNumber = self::getProcessFieldValue($processFields, 'documentNumber') ?: self::DEFAULT_DOCUMENT_NUMBER;
            $documentType = self::getProcessFieldValue($processFields, 'documentType') ?: self::DEFAULT_DOCUMENT_TYPE;

            /* general information */
            $shortDescriptionKey = (!isset($processFields['short_description']) && isset($processFields['shortDescription']))
                ? 'shortDescription'
                : 'short_description';

            $generalFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, [
                    'recordNumber',
                    'siteLocationCode',
                    'initiator',
                    'dateOfInitiation',
                    'initiationDepartment',
                    $shortDescriptionKey,
                ])
            );

            $commentFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, ['comment', 'attachment'])
            );

            /* planner grid */
            $planner = self::preparePlannerGrid($record->gridRecords ?? []);

            $tabs = [];

            $tabs[] = [
                'tab_title' => 'General Information',
                'sections' => [
                    ['type' => 'fields', 'title' => 'System Information', 'fields' => $generalFields],
                    [
                        'type' => 'planner',
                        'title' => 'Preventive Planner Information',
                        'year' => $planner['year'],
                        'total' => $planner['total'],
                        'weeks' => $planner['weeks'],
                        'tables' => $planner['tables'],
                    ],
                    ['type' => 'fields', 'title' => 'Comments & Attachments', 'fields' => $commentFields],
                ],
            ];

            $tabs[] = [
                'tab_title' => 'HOD / Designee Review',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'HOD / Designee Review',
                    'fields' => self::prepareReportFields(
                        self::getFieldsByKeys($processFields, ['hod_review_comments', 'hod_review_attachment'])
                    ),
                ]],
            ];

            $tabs[] = [
                'tab_title' => 'QA Review',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'QA Review',
                    'fields' => self::prepareReportFields(
                        self::getFieldsByKeys($processFields, ['user_dept_review_comments', 'user_dept_review_attachment'])
                    ),
                ]],
            ];

            $tabs[] = [
                'tab_title' => 'QA Approval Review',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'QA Approval Review',
                    'fields' => self::prepareReportFields(
                        self::getFieldsByKeys($processFields, ['qa_review_comments', 'qa_review_attachment'])
                    ),
                ]],
            ];

            /* cancellation tab only when the record was cancelled */
            $cancellationRemark = self::getProcessFieldValue($processFields, 'cancellation_remark');
            $cancellationAttachment = self::getProcessFieldValue($processFields, 'cancellation_attachment');
            if (!empty($cancellationRemark) || (is_array($cancellationAttachment) && !empty($cancellationAttachment))) {
                $tabs[] = [
                    'tab_title' => 'Cancellation',
                    'sections' => [[
                        'type' => 'fields',
                        'title' => 'Cancellation',
                        'fields' => self::prepareReportFields(
                            self::getFieldsByKeys($processFields, ['cancellation_remark', 'cancellation_attachment'])
                        ),
                    ]],
                ];
            }

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name ?? 'Preventive Maintenance Planner',
                    'document_number' => $documentNumber,
                    'document_type' => $documentType,
                ],
                'tabs' => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            /* generate PDF: landscape, all months of the year are printed side by side */
            $pdf = Pdf::loadView('Reports.User.PreventiveMaintenancePlannerReport', $data);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('preventive-maintenance-planner-' . $record->id . '.pdf');
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to generate preventive maintenance planner report: ' . $e->getMessage(),
                500
            );
        }
    }

    /*
     * build the planner:
     * - weeks: every week of the year (month, week number, day of month)
     * - tables: equipment rows split across pages
     */
    private static function preparePlannerGrid($gridRecords)
    {
        $monthNames = [
            'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March',
            'Apr' => 'April', 'May' => 'May', 'Jun' => 'June',
            'Jul' => 'July', 'Aug' => 'August', 'Sep' => 'September',
            'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December',
        ];

        $rows = [];
        $weekDatesData = [];

        foreach ($gridRecords as $gridRecord) {
            /* week header dates are stored once in grid_records.week_dates_data */
            $storedWeekDates = self::decodeArray($gridRecord->week_dates_data ?? null);
            if (!empty($storedWeekDates)) {
                $weekDatesData = $storedWeekDates;
            }

            foreach (self::decodeArray($gridRecord->grid_data) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                /* normalize values such as ['key' => 'location', 'value' => 'IND'] */
                $normalized = [];
                foreach ($row as $key => $value) {
                    if (is_array($value) && isset($value['key'])) {
                        $normalized[$value['key']] = $value;
                    } else {
                        $normalized[$key] = $value;
                    }
                }

                $weeklyPreventive = self::decodeArray(self::getRawGridValue($normalized, 'weeklyPreventive'));

                /* P / A values for every month and week */
                $schedule = [];
                foreach ($monthNames as $monthKey => $monthLabel) {
                    $monthValues = $weeklyPreventive[$monthKey] ?? $weeklyPreventive[$monthLabel] ?? [];

                    /* some forms wrap the month map in an additional value property */
                    if (is_array($monthValues) && isset($monthValues['value']) && is_array($monthValues['value'])) {
                        $monthValues = $monthValues['value'];
                    }
                    $monthValues = self::decodeArray($monthValues);

                    for ($weekIndex = 0; $weekIndex < 5; $weekIndex++) {
                        $weekValue = self::decodeArray($monthValues[$weekIndex] ?? []);

                        $schedule[$monthKey][$weekIndex] = [
                            'planned' => self::extractGridSimpleValue($weekValue['planned'] ?? $weekValue['p'] ?? null),
                            'actual' => self::extractGridSimpleValue($weekValue['actual'] ?? $weekValue['executed'] ?? $weekValue['a'] ?? null),
                        ];
                    }
                }

                $rows[] = [
                    'name' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentName'] ?? $normalized['equipmentName'] ?? null
                    ),
                    'code' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentId'] ?? $normalized['equipmentId'] ?? $normalized['equipmentCode'] ?? null
                    ),
                    'location' => self::extractGridSimpleValue($normalized['location'] ?? null),
                    'remark' => self::extractGridSimpleValue($normalized['remark'] ?? $normalized['remarks'] ?? null),
                    'schedule' => $schedule,
                ];
            }
        }

        /* month headers with their week numbers and day of month */
        $months = [];
        $year = null;
        $hasAnyDate = false;

        foreach ($monthNames as $monthKey => $monthLabel) {
            $dates = self::decodeArray($weekDatesData[$monthKey] ?? $weekDatesData[$monthLabel] ?? []);

            $weeks = [];
            for ($weekIndex = 0; $weekIndex < 5; $weekIndex++) {
                $rawDate = $dates[$weekIndex] ?? null;

                if ($rawDate !== null && $rawDate !== '') {
                    $hasAnyDate = true;

                    if ($year === null && is_string($rawDate) && preg_match('/^(\d{4})-/', $rawDate, $match)) {
                        $year = $match[1];
                    }
                }

                $weeks[] = [
                    'number' => $weekIndex + 1,
                    'index' => $weekIndex,
                    'date' => self::formatPlannerDay($rawDate),
                ];
            }

            $months[] = [
                'key' => $monthKey,
                'name' => $monthLabel,
                'weeks' => $weeks,
            ];
        }

        /*
         * weeks without a date are disabled in the form, so they are removed completely
         * (a week is only kept if it somehow holds data). When no dates are saved at all,
         * nothing is removed so the equipment is still printed.
         */
        if ($hasAnyDate) {
            foreach ($months as $monthIndex => $month) {
                $keptWeeks = [];

                foreach ($month['weeks'] as $week) {
                    if (!empty($week['date']) || self::weekHasData($rows, $month['key'], $week['index'])) {
                        $keptWeeks[] = $week;
                    }
                }

                $months[$monthIndex]['weeks'] = $keptWeeks;
            }

            /* months without any week are not printed */
            $months = array_values(array_filter($months, fn ($month) => !empty($month['weeks'])));
        }

        /* flat list of week columns */
        $plannerWeeks = [];
        foreach ($months as $month) {
            foreach ($month['weeks'] as $position => $week) {
                $plannerWeeks[] = [
                    'month_key' => $month['key'],
                    'month_name' => $month['name'],
                    'month_rowspan' => $position === 0 ? count($month['weeks']) : 0,
                    'number' => $week['number'],
                    'index' => $week['index'],
                    'date' => $week['date'],
                ];
            }
        }

        /* first page shares space with General Information, later pages hold EQUIPMENT_PER_PAGE rows */
        $total = count($rows);
        $tables = [];

        if ($total > 0) {
            $firstChunk = array_slice($rows, 0, self::FIRST_PAGE_EQUIPMENT, true);

            if (!empty($firstChunk)) {
                $keys = array_keys($firstChunk);

                $tables[] = [
                    'from' => reset($keys) + 1,
                    'to' => end($keys) + 1,
                    'equipment' => $firstChunk,
                ];
            }

            $remainingRows = array_slice($rows, self::FIRST_PAGE_EQUIPMENT, null, true);

            foreach (array_chunk($remainingRows, self::EQUIPMENT_PER_PAGE, true) as $equipmentChunk) {
                $keys = array_keys($equipmentChunk);

                $tables[] = [
                    'from' => reset($keys) + 1,
                    'to' => end($keys) + 1,
                    'equipment' => $equipmentChunk,
                ];
            }
        }

        return [
            'year' => $year,
            'total' => $total,
            'weeks' => $plannerWeeks,
            'tables' => $tables,
        ];
    }

    /* true when any equipment has a P / A value in the given month and week */
    private static function weekHasData($rows, $monthKey, $weekIndex)
    {
        foreach ($rows as $row) {
            $cell = $row['schedule'][$monthKey][$weekIndex] ?? [];

            if (($cell['planned'] ?? null) !== null || ($cell['actual'] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /* day of month only (04), null when the date is not set */
    private static function formatPlannerDay($date)
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            return Carbon::parse($date)->format('d');
        } catch (\Exception $e) {
            return is_scalar($date) ? (string) $date : null;
        }
    }

    /* decode json string / array safely */
    private static function decodeArray($value)
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    private static function extractGridSimpleValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            if (array_key_exists('value', $value)) {
                return self::extractGridSimpleValue($value['value']);
            }

            if (array_key_exists('name', $value)) {
                return (string) $value['name'];
            }
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return null;
    }

    private static function getRawGridValue($row, $key)
    {
        if (!array_key_exists($key, $row)) {
            return null;
        }

        $value = $row[$key];

        if (is_array($value) && array_key_exists('value', $value)) {
            return $value['value'];
        }

        return $value;
    }

    private static function getProcessFieldValue($fields, $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    private static function getFieldsByKeys($fields, array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    /* prepare report fields */
    private static function prepareReportFields($fields)
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? '';
            $value = $field['value'] ?? null;

            if (str_contains(strtolower($key), 'attachment')) {
                if (!is_array($value) || empty($value)) {
                    $value = '-';
                }
            } elseif (is_array($value)) {
                if (isset($value['name'])) {
                    $value = $value['name'];
                } elseif (empty($value)) {
                    $value = '-';
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($value === null || $value === '') {
                $value = '-';
            }

            $result[] = [
                'key' => $key,
                'label' => $field['label'] ?? self::formatFieldLabel($key),
                'value' => $value,
            ];
        }

        return $result;
    }

    private static function getAttachmentsByField($attachments, $attachmentField)
    {
        if (!is_array($attachments)) {
            return [];
        }

        $result = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            if (($attachment['attachment_field'] ?? null) !== $attachmentField) {
                continue;
            }

            $result[] = [
                'attachment_field' => $attachmentField,
                'name' => $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
            ];
        }

        return $result;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}