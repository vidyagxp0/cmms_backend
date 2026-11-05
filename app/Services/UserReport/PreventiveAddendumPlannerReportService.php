<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveAddendumPlannerReportService
{
    private const MONTHS = [
        'jan' => 'January',
        'feb' => 'February',
        'mar' => 'March',
        'apr' => 'April',
        'may' => 'May',
        'jun' => 'June',
        'jul' => 'July',
        'aug' => 'August',
        'sep' => 'September',
        'oct' => 'October',
        'nov' => 'November',
        'dec' => 'December',
    ];

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

            $processFields = [];

            foreach (self::decodeArray($record->process_data) as $field) {
                if (!is_array($field) || empty($field['key'])) {
                    continue;
                }

                $processFields[$field['key']] = [
                    'key' => $field['key'],
                    'label' => $field['label'] ?? self::formatLabel($field['key']),
                    'value' => $field['value'] ?? null,
                ];
            }

            $attachments = self::decodeArray($record->attachments);

            foreach ($processFields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachmentsByField($attachments, $key);
                }
            }
            unset($field);

            $generalFields = self::prepareFields([
                [
                    'key' => 'documentNumber',
                    'label' => 'Document Number',
                    'value' => self::value($processFields, 'documentNumber'),
                ],
                [
                    'key' => 'documentType',
                    'label' => 'Document Type',
                    'value' => self::value($processFields, 'documentType'),
                ],
                [
                    'key' => 'recordNumber',
                    'label' => 'Record Number',
                    'value' => self::value($processFields, 'recordNumber'),
                ],
                [
                    'key' => 'initiator',
                    'label' => 'Initiator',
                    'value' => $record->initiator?->name
                        ?? self::value($processFields, 'initiator'),
                ],
                [
                    'key' => 'dateOfInitiation',
                    'label' => 'Date of Initiation',
                    'value' => self::formatDate(
                        self::value($processFields, 'dateOfInitiation')
                        ?? $record->initiation_date
                    ),
                ],
                [
                    'key' => 'initiationDepartment',
                    'label' => 'Initiation Department',
                    'value' => $record->department?->name
                        ?? self::value($processFields, 'initiationDepartment'),
                ],
                [
                    'key' => 'short_description',
                    'label' => 'Short Description',
                    'value' => $record->short_description
                        ?: self::value($processFields, 'short_description'),
                ],
            ]);

            $instrumentFields = self::prepareFields(
                self::getFieldsByKeys($processFields, ['year', 'block'])
            );

            $grid = self::prepareGrid($record->gridRecords);

            $sections = [
                [
                    'type' => 'fields',
                    'title' => 'General Information',
                    'fields' => $generalFields,
                ],
                [
                    'type' => 'fields',
                    'title' => 'Field Instrument Information',
                    'fields' => $instrumentFields,
                ],
                [
                    'type' => 'planner',
                    'title' => 'Preventive Maintenance Schedule',
                    'year' => self::value($processFields, 'year') ?: $grid['year'],
                    'rows' => $grid['rows'],
                    'months' => $grid['months'],
                ],
                [
                    'type' => 'fields',
                    'title' => 'Comments & Attachments',
                    'fields' => self::prepareFields(
                        self::getFieldsByKeys($processFields, [
                            'comment',
                            'attachment',
                        ])
                    ),
                ],
                [
                    'type' => 'fields',
                    'title' => 'HOD / Designee Review',
                    'fields' => self::prepareFields(
                        self::getFieldsByKeys($processFields, [
                            'hod_review_comments',
                            'hod_review_attachment',
                        ])
                    ),
                ],
                [
                    'type' => 'fields',
                    'title' => 'QA Approval Review',
                    'fields' => self::prepareFields(
                        self::getFieldsByKeys($processFields, [
                            'qa_review_comments',
                            'qa_review_attachment',
                        ])
                    ),
                ],
            ];

            $cancellationRemark = self::value($processFields, 'cancellation_remark');
            $cancellationAttachment = self::value($processFields, 'cancellation_attachment');

            if (!empty($cancellationRemark) || !empty($cancellationAttachment)) {
                $sections[] = [
                    'type' => 'fields',
                    'title' => 'Cancellation Details',
                    'fields' => self::prepareFields(
                        self::getFieldsByKeys($processFields, [
                            'cancellation_remark',
                            'cancellation_attachment',
                        ])
                    ),
                ];
            }

            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => 'Preventive Addendum Planner',
                    'document_number' => self::value($processFields, 'documentNumber') ?: '-',
                    'document_type' => self::value($processFields, 'documentType') ?: '-',
                ],
                'sections' => $sections,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView(
                'Reports.User.PreventiveAddendumPlannerReport',
                $data
            );

            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');
            $pdf->setOption('isHtml5ParserEnabled', true);
            $pdf->setOption('isRemoteEnabled', true);

            return $pdf->stream(
                'preventive-addendum-planner-' . $record->id . '.pdf'
            );
        } catch (\Throwable $e) {
            \Log::error('PREVENTIVE ADDENDUM REPORT ERROR', [
                'record_id' => $id,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ResponseHelper::error(
                'Failed to generate preventive addendum planner report: ' . $e->getMessage(),
                500
            );
        }
    }

    private static function prepareGrid($gridRecords): array
    {
        $rows = [];
        $year = null;

        foreach ($gridRecords ?? [] as $gridRecord) {
            $gridData = self::decodeArray($gridRecord->grid_data);

            foreach ($gridData as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $normalized = [];

                foreach ($row as $key => $field) {
                    if ($key === 'row_id') {
                        continue;
                    }

                    $normalized[$key] = self::extractValue($field);
                }

                $planned = [];
                $executed = [];

                foreach (self::MONTHS as $monthKey => $monthName) {
                    $plannedDate = $normalized[$monthKey . '_planned'] ?? null;
                    $executedDate = $normalized[$monthKey . '_executed'] ?? null;

                    $planned[$monthKey] = self::formatDate($plannedDate);
                    $executed[$monthKey] = self::formatDate($executedDate);

                    if (!$year && $plannedDate) {
                        $year = self::extractYear($plannedDate);
                    }

                    if (!$year && $executedDate) {
                        $year = self::extractYear($executedDate);
                    }
                }

                $rows[] = [
                    'name' => $normalized['equipmentInstrumentName'] ?? '-',
                    'code' => $normalized['equipmentInstrumentId'] ?? '-',
                    'location' => $normalized['location'] ?? '-',
                    'planned' => $planned,
                    'executed' => $executed,
                    'remark' => $normalized['remark'] ?? '-',
                ];
            }
        }

        $months = [];

        foreach (self::MONTHS as $key => $name) {
            $months[] = [
                'key' => $key,
                'name' => $name,
            ];
        }

        return [
            'year' => $year,
            'months' => $months,
            'rows' => $rows,
        ];
    }

    private static function extractValue($field)
    {
        if (is_array($field) && array_key_exists('value', $field)) {
            return $field['value'];
        }

        return $field;
    }

    private static function extractYear($date): ?string
    {
        if (!$date || !is_scalar($date)) {
            return null;
        }

        try {
            return Carbon::parse($date)->format('Y');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function formatDate($value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (!is_scalar($value)) {
            return '-';
        }

        try {
            return Carbon::parse((string) $value)->format('d/m/Y');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private static function decodeArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private static function value(array $fields, string $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    private static function getFieldsByKeys(array $fields, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    private static function prepareFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? '';
            $value = $field['value'] ?? null;

            if (str_contains(strtolower($key), 'attachment')) {
                $value = self::formatAttachments($value);
            } elseif (is_array($value)) {
                $value = empty($value)
                    ? '-'
                    : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            if ($value === null || $value === '') {
                $value = '-';
            }

            $result[] = [
                'key' => $key,
                'label' => $field['label'] ?? self::formatLabel($key),
                'value' => $value,
            ];
        }

        return $result;
    }

    private static function getAttachmentsByField(array $attachments, string $fieldKey): array
    {
        $result = [];

        foreach ($attachments as $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            if (($attachment['attachment_field'] ?? null) !== $fieldKey) {
                continue;
            }

            $result[] = [
                'name' => $attachment['name']
                    ?? $attachment['file_name']
                    ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => $attachment['url'] ?? null,
            ];
        }

        return $result;
    }

    private static function formatAttachments($value): string
    {
        if (!is_array($value) || empty($value)) {
            return '-';
        }

        $names = [];

        foreach ($value as $attachment) {
            if (is_array($attachment)) {
                $names[] = $attachment['name']
                    ?? $attachment['file_name']
                    ?? 'Attachment';
            } else {
                $names[] = (string) $attachment;
            }
        }

        return implode(', ', $names);
    }

    private static function formatLabel(string $key): string
    {
        $key = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key);
        $key = str_replace(['_', '-'], ' ', $key);

        return ucwords(trim($key));
    }
}