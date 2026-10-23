<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveMaintenancePlannerReportService
{
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

            $processData = $record->process_data;

            if (is_string($processData)) {
                $processData = json_decode($processData, true);
            }

            if (!is_array($processData)) {
                $processData = [];
            }

            $processFields = [];

            foreach ($processData as $field) {
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

            $recordAttachments = $record->attachments;

            if (is_string($recordAttachments)) {
                $decoded = json_decode($recordAttachments, true);
                $recordAttachments = is_array($decoded) ? $decoded : [];
            }

            if (!is_array($recordAttachments)) {
                $recordAttachments = [];
            }

            foreach ($processFields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachmentsByField($recordAttachments, $key);
                }
            }

            unset($field);

            $shortDescriptionKey = (
                !isset($processFields['short_description']) &&
                isset($processFields['shortDescription'])
            ) ? 'shortDescription' : 'short_description';

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

            $preventiveInformationFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, ['comment', 'attachment'])
            );

            $hodReviewFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, [
                    'hod_review_comments',
                    'hod_review_attachment',
                ])
            );

            $qaReviewFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, [
                    'user_dept_review_comments',
                    'user_dept_review_attachment',
                ])
            );

            $qaApprovalFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, [
                    'qa_review_comments',
                    'qa_review_attachment',
                ])
            );

            $plannerGrid = self::preparePlannerGrid($record->gridRecords ?? []);

            $tabs = [
                [
                    'tab_title' => 'General Information',
                    'sections' => [
                        [
                            'title' => 'Process Record Details',
                            'fields' => $generalFields,
                        ],
                        [
                            'title' => 'Preventive Maintenance Information',
                            'fields' => $preventiveInformationFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'HOD / Designee Review',
                    'sections' => [
                        [
                            'title' => 'HOD / Designee Review',
                            'fields' => $hodReviewFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'QA Review',
                    'sections' => [
                        [
                            'title' => 'QA Review',
                            'fields' => $qaReviewFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'QA Approval Review',
                    'sections' => [
                        [
                            'title' => 'QA Approval Review',
                            'fields' => $qaApprovalFields,
                        ],
                    ],
                ],
            ];

            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name ?? 'Preventive Maintenance Planner',
                ],
                'record' => [
                    'record_id' => $record->id,
                    'record_number' => self::getProcessFieldValue($processFields, 'recordNumber'),
                    'site_location_code' => self::getProcessFieldValue($processFields, 'siteLocationCode'),
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => $record->initiation_date
                        ? Carbon::parse($record->initiation_date)->format('d-m-Y H:i')
                        : null,
                ],
                'tabs' => $tabs,
                'plannerGrid' => $plannerGrid,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView(
                'Reports.User.PreventiveMaintenancePlannerReport',
                $data
            );

            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream(
                'preventive-maintenance-planner-' . $record->id . '.pdf'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to generate preventive maintenance planner report: ' . $e->getMessage(),
                500
            );
        }
    }

    private static function preparePlannerGrid($gridRecords)
    {
        $rows = [];
        $weekDatesData = [];
        $monthNames = [
            'Jan' => 'January', 'Feb' => 'February', 'Mar' => 'March',
            'Apr' => 'April', 'May' => 'May', 'Jun' => 'June',
            'Jul' => 'July', 'Aug' => 'August', 'Sep' => 'September',
            'Oct' => 'October', 'Nov' => 'November', 'Dec' => 'December',
        ];

        foreach ($gridRecords as $gridRecord) {
            // Week header dates are stored once in grid_records.week_dates_data.
            $storedWeekDates = $gridRecord->week_dates_data ?? [];
            if (is_string($storedWeekDates)) {
                $storedWeekDates = json_decode($storedWeekDates, true);
            }
            if (is_array($storedWeekDates) && !empty($storedWeekDates)) {
                $weekDatesData = $storedWeekDates;
            }

            $gridData = $gridRecord->grid_data;
            if (is_string($gridData)) {
                $gridData = json_decode($gridData, true);
            }
            if (!is_array($gridData)) {
                continue;
            }

            foreach ($gridData as $rowIndex => $row) {
                if (!is_array($row)) {
                    continue;
                }

                // Normalize values such as ['key' => 'location', 'value' => 'IND'].
                $normalized = [];
                foreach ($row as $key => $value) {
                    if (is_array($value) && isset($value['key'])) {
                        $normalized[$value['key']] = $value;
                    } else {
                        $normalized[$key] = $value;
                    }
                }

                $weeklyPreventive = self::getRawGridValue($normalized, 'weeklyPreventive');
                if (is_string($weeklyPreventive)) {
                    $decoded = json_decode($weeklyPreventive, true);
                    $weeklyPreventive = is_array($decoded) ? $decoded : [];
                }
                if (!is_array($weeklyPreventive)) {
                    $weeklyPreventive = [];
                }

                $rows[] = [
                    'row_id' => $normalized['row_id'] ?? ($rowIndex + 1),
                    'equipmentInstrumentName' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentName'] ?? $normalized['equipmentName'] ?? null
                    ),
                    'equipmentInstrumentId' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentId'] ?? $normalized['equipmentId'] ?? $normalized['equipmentCode'] ?? null
                    ),
                    'location' => self::extractGridSimpleValue($normalized['location'] ?? null),
                    'remark' => self::extractGridSimpleValue($normalized['remark'] ?? $normalized['remarks'] ?? null),
                    'weeklyPreventive' => $weeklyPreventive,
                ];
            }
        }

        // Build a month-first schedule so the PDF can mirror the form's month/week/P-A grid.
        $monthlySchedules = [];
        foreach ($monthNames as $monthKey => $monthLabel) {
            $dates = $weekDatesData[$monthKey] ?? $weekDatesData[$monthLabel] ?? [];
            if (is_string($dates)) {
                $decoded = json_decode($dates, true);
                $dates = is_array($decoded) ? $decoded : [];
            }
            if (!is_array($dates)) {
                $dates = [];
            }

            $weeks = [];
            for ($weekIndex = 0; $weekIndex < 5; $weekIndex++) {
                $date = $dates[$weekIndex] ?? null;
                $weeks[] = [
                    'number' => $weekIndex + 1,
                    'date' => $date,
                ];
            }

            $monthRows = [];
            foreach ($rows as $row) {
                $monthValues = $row['weeklyPreventive'][$monthKey]
                    ?? $row['weeklyPreventive'][$monthLabel]
                    ?? [];

                // Some forms may wrap the month map in an additional value property.
                if (is_array($monthValues) && isset($monthValues['value']) && is_array($monthValues['value'])) {
                    $monthValues = $monthValues['value'];
                }

                $values = [];
                for ($weekIndex = 0; $weekIndex < 5; $weekIndex++) {
                    $weekValue = $monthValues[$weekIndex] ?? [];
                    if (is_string($weekValue)) {
                        $decoded = json_decode($weekValue, true);
                        $weekValue = is_array($decoded) ? $decoded : [];
                    }
                    if (!is_array($weekValue)) {
                        $weekValue = [];
                    }

                    $values[] = [
                        'planned' => self::extractGridSimpleValue($weekValue['planned'] ?? $weekValue['p'] ?? null),
                        'actual' => self::extractGridSimpleValue($weekValue['actual'] ?? $weekValue['executed'] ?? $weekValue['a'] ?? null),
                    ];
                }

                $monthRows[] = [
                    'equipmentInstrumentName' => $row['equipmentInstrumentName'],
                    'equipmentInstrumentId' => $row['equipmentInstrumentId'],
                    'location' => $row['location'],
                    'remark' => $row['remark'],
                    'weeks' => $values,
                ];
            }

            $monthlySchedules[] = [
                'key' => $monthKey,
                'name' => $monthLabel,
                'weeks' => $weeks,
                'rows' => $monthRows,
            ];
        }

        return [
            'name' => 'preventiveMaintenancePlanner',
            'label' => 'Preventive Maintenance Planner',
            'weekDatesData' => $weekDatesData,
            'rows' => $rows,
            'monthlySchedules' => $monthlySchedules,
        ];
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
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                    );
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