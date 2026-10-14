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

        foreach ($gridRecords as $gridRecord) {
            $gridData = $gridRecord->grid_data;

            if (is_string($gridData)) {
                $gridData = json_decode($gridData, true);
            }

            if (!is_array($gridData)) {
                continue;
            }

            $rawRows = [];

            foreach ($gridData as $row) {
                if (!is_array($row)) {
                    continue;
                }

                if (
                    isset($row['equipmentInstrumentName']) ||
                    isset($row['equipmentInstrumentId']) ||
                    isset($row['monthlyPreventive'])
                ) {
                    $rawRows[] = $row;
                    continue;
                }

                $normalizedRow = [];

                foreach ($row as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $key = $field['key'] ?? null;

                    if (!$key) {
                        continue;
                    }

                    $normalizedRow[$key] = $field;
                }

                if (!empty($normalizedRow)) {
                    $rawRows[] = $normalizedRow;
                }
            }

            foreach ($rawRows as $rowIndex => $row) {
                $monthlyPreventive = self::getRawGridValue($row, 'monthlyPreventive');

                if (is_string($monthlyPreventive)) {
                    $decoded = json_decode($monthlyPreventive, true);
                    $monthlyPreventive = is_array($decoded) ? $decoded : [];
                }

                if (!is_array($monthlyPreventive)) {
                    $monthlyPreventive = [];
                }

                $schedule = [];

                foreach ($monthlyPreventive as $month => $monthData) {
                    if (!is_array($monthData)) {
                        continue;
                    }

                    $plannedDate = $monthData['plannedDate'] ?? $monthData['Planned Date'] ?? null;
                    $executeDate = $monthData['executeDate'] ?? $monthData['Execute Date'] ?? null;

                    if (empty($plannedDate) && empty($executeDate)) {
                        continue;
                    }

                    $schedule[] = [
                        'month' => $month,
                        'plannedDate' => $plannedDate,
                        'executeDate' => $executeDate,
                    ];
                }

                $frequency = self::getRawGridValue($row, 'preventiveFrequency');

                $rows[] = [
                    'row_id' => $row['row_id'] ?? ($rowIndex + 1),
                    'equipmentInstrumentName' => $row['equipmentInstrumentName'] ?? $row['equipmentName'] ?? null,
                    'equipmentInstrumentId' => $row['equipmentInstrumentId']
                        ?? $row['equipmentId']
                        ?? $row['equipmentCode']
                        ?? null,
                    'block' => $row['block'] ?? null,
                    'department' => $row['department'] ?? null,
                    'location' => $row['location'] ?? null,
                    'preventiveFrequency' => $row['preventiveFrequency'] ?? null,
                    'preventiveFrequencyStartDate' => $row['preventiveFrequencyStartDate'] ?? null,
                    'previousPreventiveDate' => $row['previousPreventiveDate'] ?? null,
                    'nextPreventiveDate' => $row['nextPreventiveDate'] ?? null,
                    'make' => $row['make'] ?? null,
                    'model' => $row['model'] ?? null,
                    'remark' => $row['remark'] ?? null,
                    'frequencyLabel' => self::getPreventiveFrequencyLabel(
                        self::extractGridSimpleValue($frequency)
                    ),
                    'schedule' => $schedule,
                ];
            }
        }

        return [
            'name' => 'preventiveMaintenancePlanner',
            'label' => 'Preventive Maintenance Planner',
            'rows' => $rows,
        ];
    }

    private static function getPreventiveFrequencyLabel(?string $frequency): string
    {
        if (!$frequency) {
            return 'Preventive Maintenance';
        }

        $normalized = strtolower(trim(str_replace(['-', '_'], ' ', $frequency)));

        $labels = [
            'monthly' => 'Monthly Preventive Maintenance',
            'quarterly' => 'Quarterly Preventive Maintenance',
            'half yearly' => 'Half-Yearly Preventive Maintenance',
            'halfyearly' => 'Half-Yearly Preventive Maintenance',
            'yearly' => 'Yearly Preventive Maintenance',
            'two yearly' => 'Two-Yearly Preventive Maintenance',
            'twoyearly' => 'Two-Yearly Preventive Maintenance',
        ];

        return $labels[$normalized] ?? ucwords($frequency) . ' Preventive Maintenance';
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