<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveMaintenancePlannerReportService
{
    /*
     * equipment printed per page of the planner grid (each equipment has a Planned and an Executed row):
     * - page 1 shares the page with General Information, so it holds fewer equipment
     * - every following page holds EQUIPMENT_PER_PAGE equipment (the last page holds the remainder)
     */
    private const FIRST_PAGE_EQUIPMENT = 8;
    private const EQUIPMENT_PER_PAGE = 20;

    /* shown in the header row when process_data has no documentNumber / documentType */
    private const DEFAULT_DOCUMENT_NUMBER = '-';
    private const DEFAULT_DOCUMENT_TYPE = '-';

    /* month columns of the planner */
    private const MONTHS = [
        'Jan' => 'January',
        'Feb' => 'February',
        'Mar' => 'March',
        'Apr' => 'April',
        'May' => 'May',
        'Jun' => 'June',
        'Jul' => 'July',
        'Aug' => 'August',
        'Sep' => 'September',
        'Oct' => 'October',
        'Nov' => 'November',
        'Dec' => 'December',
    ];

    /* generate preventive maintenance planner report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'gridRecords'])->findOrFail($id);

            /* process data */
            $processFields = [];

            foreach (self::decodeArray($record->process_data) as $field) {
                if (!is_array($field) || empty($field['key'])) {
                    continue;
                }

                $key = $field['key'];

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
            $shortDescriptionKey = (
                !isset($processFields['short_description'])
                && isset($processFields['shortDescription'])
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

            /* general comments followed by the HOD, user department and QA review comments / attachments */
            $commentFields = self::prepareReportFields(
                array_merge(
                    self::getFieldsByKeys($processFields, ['comment', 'attachment']),
                    self::getReviewFields($processFields)
                )
            );

            /* planner grid */
            $planner = self::preparePlannerGrid($record->gridRecords ?? []);

            $tabs = [
                [
                    'tab_title' => 'General Information',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'System Information',
                            'fields' => $generalFields,
                        ],
                        [
                            'type' => 'planner',
                            'title' => 'Preventive Planner Information',
                            'year' => $planner['year'],
                            'total' => $planner['total'],
                            'months' => $planner['months'],
                            'tables' => $planner['tables'],
                        ],
                        [
                            /* no heading: comments and review lines are printed as plain rows */
                            'type' => 'fields',
                            'title' => null,
                            'fields' => $commentFields,
                        ],
                    ],
                ],
            ];

            /* cancellation tab only when the record was cancelled */
            $cancellationRemark = self::getProcessFieldValue($processFields, 'cancellation_remark');
            $cancellationAttachment = self::getProcessFieldValue($processFields, 'cancellation_attachment');

            if (
                !empty($cancellationRemark)
                || (is_array($cancellationAttachment) && !empty($cancellationAttachment))
            ) {
                $tabs[] = [
                    'tab_title' => 'Cancellation',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'Cancellation',
                            'fields' => self::prepareReportFields(
                                self::getFieldsByKeys($processFields, [
                                    'cancellation_remark',
                                    'cancellation_attachment',
                                ])
                            ),
                        ],
                    ],
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

            $pdf = Pdf::loadView('Reports.User.PreventiveMaintenancePlannerReport', $data);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('preventive-maintenance-planner-' . $record->id . '.pdf');
        } catch (\Throwable $e) {
            return ResponseHelper::error(
                'Failed to generate preventive maintenance planner report: ' . $e->getMessage(),
                500
            );
        }
    }

    /* HOD, user department and QA review comments / attachments, in that order */
    private static function getReviewFields(array $fields): array
    {
        $groups = [
            'hod' => [],
            'user_department' => [],
            'qa' => [],
        ];

        foreach ($fields as $key => $field) {
            $searchText = strtolower(str_replace(
                ['_', '-', '/', '\\', ' '],
                '',
                (string) $key . (string) ($field['label'] ?? '')
            ));

            if (!str_contains($searchText, 'comment') && !str_contains($searchText, 'attachment')) {
                continue;
            }

            if (str_contains($searchText, 'hod') || str_contains($searchText, 'designee')) {
                $groups['hod'][] = $field;
            } elseif (str_contains($searchText, 'userdept') || str_contains($searchText, 'userdepartment')) {
                $groups['user_department'][] = $field;
            } elseif (str_contains($searchText, 'qa')) {
                $groups['qa'][] = $field;
            }
        }

        return array_merge($groups['hod'], $groups['user_department'], $groups['qa']);
    }

    /*
     * build the planner as in the form:
     * every equipment has a Planned row and an Executed row with one date per month
     */
    private static function preparePlannerGrid($gridRecords): array
    {
        $rows = [];
        $year = null;

        foreach ($gridRecords as $gridRecord) {
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

                /* planned / executed date of every month (jan_planned, jan_executed, ...) */
                $planned = [];
                $executed = [];

                foreach (self::MONTHS as $monthKey => $monthName) {
                    $prefix = strtolower($monthKey);

                    $plannedDate = self::extractGridSimpleValue($normalized[$prefix . '_planned'] ?? null);
                    $executedDate = self::extractGridSimpleValue($normalized[$prefix . '_executed'] ?? null);

                    if ($plannedDate !== null) {
                        $year = $year ?? self::extractYear($plannedDate);
                        $planned[$monthKey] = self::formatPlannerDate($plannedDate);
                    }

                    if ($executedDate !== null) {
                        $year = $year ?? self::extractYear($executedDate);
                        $executed[$monthKey] = self::formatPlannerDate($executedDate);
                    }
                }

                $rows[] = [
                    'name' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentName'] ?? $normalized['equipmentName'] ?? null
                    ),
                    'code' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentId']
                        ?? $normalized['equipmentId']
                        ?? $normalized['equipmentCode']
                        ?? null
                    ),
                    'location' => self::extractGridSimpleValue($normalized['location'] ?? null),
                    'remark' => self::extractGridSimpleValue($normalized['remark'] ?? $normalized['remarks'] ?? null),
                    'planned' => $planned,
                    'executed' => $executed,
                ];
            }
        }

        /* month columns */
        $months = [];

        foreach (self::MONTHS as $monthKey => $monthName) {
            $months[] = ['key' => $monthKey, 'name' => $monthName];
        }

        /* split the equipment into pages: FIRST_PAGE_EQUIPMENT on page 1, EQUIPMENT_PER_PAGE on every other page */
        $total = count($rows);
        $tables = [];

        if ($total > 0) {
            $chunks = [array_slice($rows, 0, self::FIRST_PAGE_EQUIPMENT, true)];

            $remainingRows = array_slice($rows, self::FIRST_PAGE_EQUIPMENT, null, true);

            foreach (array_chunk($remainingRows, self::EQUIPMENT_PER_PAGE, true) as $chunk) {
                $chunks[] = $chunk;
            }

            foreach ($chunks as $chunk) {
                $keys = array_keys($chunk);

                $tables[] = [
                    'from' => reset($keys) + 1,
                    'to' => end($keys) + 1,
                    'equipment' => $chunk,
                ];
            }
        }

        return [
            'year' => $year,
            'total' => $total,
            'months' => $months,
            'tables' => $tables,
        ];
    }

    /* year found inside a date value */
    private static function extractYear($date): ?string
    {
        if (is_string($date) && preg_match('/\b(20\d{2})\b/', $date, $match)) {
            return $match[1];
        }

        return null;
    }

    /* date as dd/mm/yyyy (same as the form), anything that is not a date is returned as it is */
    private static function formatPlannerDate($date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = trim((string) $date);

        try {
            if (preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{4}$/', $date)) {
                return Carbon::createFromFormat('d/m/Y', str_replace('-', '/', $date))->format('d/m/Y');
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return Carbon::parse($date)->format('d/m/Y');
            }
        } catch (\Throwable $e) {
            return $date;
        }

        return $date;
    }

    /* decode json string / array safely */
    private static function decodeArray($value): array
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

            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    private static function getProcessFieldValue($fields, $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    private static function getFieldsByKeys($fields, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    /* prepare report fields (empty values become "-") */
    private static function prepareReportFields($fields): array
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

    private static function getAttachmentsByField($attachments, $attachmentField): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $result = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment) || ($attachment['attachment_field'] ?? null) !== $attachmentField) {
                continue;
            }

            $result[] = [
                'name' => $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment',
                'url' => $attachment['url'] ?? null,
            ];
        }

        return $result;
    }

    private static function formatFieldLabel($key): string
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}