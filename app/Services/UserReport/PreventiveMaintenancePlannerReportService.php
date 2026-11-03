<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveMaintenancePlannerReportService
{
    private const FIRST_PAGE_EQUIPMENT = 20;
    private const EQUIPMENT_PER_PAGE = 10;

    private const DEFAULT_DOCUMENT_NUMBER = '-';
    private const DEFAULT_DOCUMENT_TYPE = '-';

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

    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with([
                'process',
                'gridRecords',
            ])->findOrFail($id);

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

            $recordAttachments = self::decodeArray($record->attachments);

            foreach ($processFields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachmentsByField(
                        $recordAttachments,
                        $key
                    );
                }
            }

            unset($field);

            $documentNumber = self::getProcessFieldValue(
                $processFields,
                'documentNumber'
            ) ?: self::DEFAULT_DOCUMENT_NUMBER;

            $documentType = self::getProcessFieldValue(
                $processFields,
                'documentType'
            ) ?: self::DEFAULT_DOCUMENT_TYPE;

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

            // General comments followed by HOD, User Department and QA review fields.
            $commentAndReviewFields = array_merge(
                self::getFieldsByKeys($processFields, [
                    'comment',
                    'attachment',
                ]),
                self::getReviewFields($processFields)
            );

            $commentFields = self::prepareReportFields(
                $commentAndReviewFields
            );

            $planner = self::preparePlannerGrid(
                $record->gridRecords ?? []
            );

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
                            'type' => 'fields',
                            'title' => 'Comments & Attachments',
                            'fields' => $commentFields,
                        ],
                    ],
                ],
            ];

            $cancellationRemark = self::getProcessFieldValue(
                $processFields,
                'cancellation_remark'
            );

            $cancellationAttachment = self::getProcessFieldValue(
                $processFields,
                'cancellation_attachment'
            );

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

            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name
                        ?? 'Preventive Maintenance Planner',
                    'document_number' => $documentNumber,
                    'document_type' => $documentType,
                ],
                'tabs' => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView(
                'Reports.User.PreventiveMaintenancePlannerReport',
                $data
            );

            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream(
                'preventive-maintenance-planner-' . $record->id . '.pdf'
            );

        } catch (\Throwable $e) {
            return ResponseHelper::error(
                'Failed to generate preventive maintenance planner report: '
                . $e->getMessage(),
                500
            );
        }
    }

    private static function getReviewFields(array $fields): array
    {
        $groups = [
            'hod' => [],
            'user_department' => [],
            'qa' => [],
        ];

        foreach ($fields as $key => $field) {
            $searchText = strtolower(
                str_replace(
                    ['_', '-', '/', '\\', ' '],
                    '',
                    (string) $key . (string) ($field['label'] ?? '')
                )
            );

            $isCommentOrAttachment =
                str_contains($searchText, 'comment')
                || str_contains($searchText, 'attachment');

            if (!$isCommentOrAttachment) {
                continue;
            }

            if (
                str_contains($searchText, 'hod')
                || str_contains($searchText, 'designee')
            ) {
                $groups['hod'][] = $field;
            } elseif (
                str_contains($searchText, 'userdept')
                || str_contains($searchText, 'userdepartment')
            ) {
                $groups['user_department'][] = $field;
            } elseif (str_contains($searchText, 'qa')) {
                $groups['qa'][] = $field;
            }
        }

        return array_merge(
            $groups['hod'],
            $groups['user_department'],
            $groups['qa']
        );
    }

    private static function preparePlannerGrid($gridRecords): array
    {
        $rows = [];
        $year = null;

        foreach ($gridRecords as $gridRecord) {
            foreach (self::decodeArray($gridRecord->grid_data) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalized = [];

                foreach ($row as $key => $value) {
                    if (is_array($value) && isset($value['key'])) {
                        $normalized[$value['key']] = $value;
                    } else {
                        $normalized[$key] = $value;
                    }
                }

                $planned = [];
                $executed = [];

                foreach (self::MONTHS as $monthKey => $monthName) {
                    $prefix = strtolower($monthKey);

                    $plannedDate = self::extractGridSimpleValue(
                        $normalized[$prefix . '_planned'] ?? null
                    );

                    $executedDate = self::extractGridSimpleValue(
                        $normalized[$prefix . '_executed'] ?? null
                    );

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
                        $normalized['equipmentInstrumentName']
                        ?? $normalized['equipmentName']
                        ?? null
                    ),
                    'code' => self::extractGridSimpleValue(
                        $normalized['equipmentInstrumentId']
                        ?? $normalized['equipmentId']
                        ?? $normalized['equipmentCode']
                        ?? null
                    ),
                    'location' => self::extractGridSimpleValue(
                        $normalized['location'] ?? null
                    ),
                    'remark' => self::extractGridSimpleValue(
                        $normalized['remark']
                        ?? $normalized['remarks']
                        ?? null
                    ),
                    'planned' => $planned,
                    'executed' => $executed,
                ];
            }
        }

        $months = [];

        foreach (self::MONTHS as $monthKey => $monthName) {
            $months[] = [
                'key' => $monthKey,
                'name' => $monthName,
            ];
        }

        $total = count($rows);
        $tables = [];

        if ($total > 0) {
            $firstChunk = array_slice(
                $rows,
                0,
                self::FIRST_PAGE_EQUIPMENT,
                true
            );

            if (!empty($firstChunk)) {
                $keys = array_keys($firstChunk);

                $tables[] = [
                    'from' => reset($keys) + 1,
                    'to' => end($keys) + 1,
                    'equipment' => $firstChunk,
                ];
            }

            $remainingRows = array_slice(
                $rows,
                self::FIRST_PAGE_EQUIPMENT,
                null,
                true
            );

            foreach (
                array_chunk($remainingRows, self::EQUIPMENT_PER_PAGE, true)
                as $equipmentChunk
            ) {
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
            'months' => $months,
            'tables' => $tables,
        ];
    }

    private static function extractYear($date): ?string
    {
        if (
            is_string($date)
            && preg_match('/\b(20\d{2})\b/', $date, $match)
        ) {
            return $match[1];
        }

        return null;
    }

    private static function formatPlannerDate($date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        $date = trim((string) $date);

        try {
            if (preg_match('/^\d{1,2}[\/-]\d{1,2}[\/-]\d{4}$/', $date)) {
                return Carbon::createFromFormat(
                    'd/m/Y',
                    str_replace('-', '/', $date)
                )->format('d/m/Y');
            }

            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $date)) {
                return Carbon::parse($date)->format('d/m/Y');
            }
        } catch (\Throwable $e) {
            return $date;
        }

        return $date;
    }

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

    private static function getAttachmentsByField($attachments, $attachmentField): array
    {
        if (!is_array($attachments)) {
            return [];
        }

        $result = [];

        foreach ($attachments as $attachment) {
            if (
                !is_array($attachment)
                || ($attachment['attachment_field'] ?? null) !== $attachmentField
            ) {
                continue;
            }

            $result[] = [
                'attachment_field' => $attachmentField,
                'name' => $attachment['name']
                    ?? $attachment['file_name']
                    ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
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
