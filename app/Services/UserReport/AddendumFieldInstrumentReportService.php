<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class AddendumFieldInstrumentReportService
{
    /* generate field instrument calibration addendum report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'stage', 'department', 'initiator', 'gridRecords'])->findOrFail($id);

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
                if (str_contains($key, 'attachment')) {
                    $field['value'] = self::getAttachmentsByField($recordAttachments, $key);
                }
            }
            unset($field);

            /* header values */
            $documentNumber = self::getProcessFieldValue($processFields, 'documentNumber');
            $documentType = self::getProcessFieldValue($processFields, 'documentType');
            $recordNumber = self::getProcessFieldValue($processFields, 'recordNumber');

            /* initiation date: process data value first, then the record column */
            $initiationDate = self::getProcessFieldValue($processFields, 'dateOfInitiation');
            if (empty($initiationDate) && $record->initiation_date) {
                $initiationDate = Carbon::parse($record->initiation_date)->format('d-M-Y H:i:s');
            }

            /* general information tab */
            $systemFields = self::prepareReportFields([
                ['key' => 'recordNumber', 'label' => 'Record Number', 'value' => $recordNumber],
                ['key' => 'initiator', 'label' => 'Initiator', 'value' => $record->initiator?->name ?? self::getProcessFieldValue($processFields, 'initiator')],
                ['key' => 'dateOfInitiation', 'label' => 'Date of Initiation', 'value' => $initiationDate],
                ['key' => 'initiationDepartment', 'label' => 'Initiation Department', 'value' => $record->department?->name ?? self::getProcessFieldValue($processFields, 'initiationDepartment')],
                ['key' => 'short_description', 'label' => 'Short Description', 'value' => $record->short_description ?: self::getProcessFieldValue($processFields, 'short_description')],
            ]);

            $instrumentInfoFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, ['year', 'block', 'area'])
            );

            $commentFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, ['comment', 'attachment'])
            );

            /* calibration addendum grid */
            $grid = self::prepareGrid($record->gridRecords);

            $tabs = [];

            $tabs[] = [
                'tab_title' => 'General Information',
                'sections' => [
                    ['type' => 'fields', 'title' => 'System Information', 'fields' => $systemFields],
                    ['type' => 'fields', 'title' => 'Field Instrument Information', 'fields' => $instrumentInfoFields],
                    [
                        'type' => 'grid',
                        'title' => 'Field Instrument Calibration Addendum',
                        'columns' => $grid['columns'],
                        'rows' => $grid['rows'],
                        'sr_width' => $grid['sr_width'],
                    ],
                    ['type' => 'fields', 'title' => 'Comments & Attachments', 'fields' => $commentFields],
                ],
            ];

            $tabs[] = [
                'tab_title' => 'HOD / Designee Review (Engineering Dept)',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'HOD / Designee Review (Engineering Dept)',
                    'fields' => self::prepareReportFields(self::getFieldsByKeys($processFields, ['hod_review_comments', 'hod_review_attachment'])),
                ]],
            ];

            $tabs[] = [
                'tab_title' => 'User Department Review (User Dept)',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'User Department Review (User Dept)',
                    'fields' => self::prepareReportFields(self::getFieldsByKeys($processFields, ['user_dept_review_comments', 'user_dept_review_attachment'])),
                ]],
            ];

            $tabs[] = [
                'tab_title' => 'QA Approval Review',
                'sections' => [[
                    'type' => 'fields',
                    'title' => 'QA Approval Review',
                    'fields' => self::prepareReportFields(self::getFieldsByKeys($processFields, ['qa_review_comments', 'qa_review_attachment'])),
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
                        'fields' => self::prepareReportFields(self::getFieldsByKeys($processFields, ['cancellation_remark', 'cancellation_attachment'])),
                    ]],
                ];
            }

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name ?? 'Field Instrument Calibration Addendum',
                    'document_number' => $documentNumber ?: '-',
                    'document_type' => $documentType ?: '-',
                ],
                'tabs' => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            /* generate PDF */
            $pdf = Pdf::loadView('Reports.User.AddendumFieldInstrumentPlannerReport', $data);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('process-record-' . $record->id . '.pdf');
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
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

    /* build grid columns and rows (one row per instrument) */
    private static function prepareGrid($gridRecords)
    {
        /* technical keys that should never be shown in the report */
        $skipKeys = ['row_id', 'monthlyCalibration', 'calibrationFrequencyStartDate'];

        $columns = [];
        $rows = [];

        foreach ($gridRecords ?? [] as $gridRecord) {
            foreach (self::decodeArray($gridRecord->grid_data) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $values = [];

                foreach ($row as $key => $field) {
                    if (in_array($key, $skipKeys, true)) {
                        continue;
                    }

                    if (!isset($columns[$key])) {
                        $label = (is_array($field) && !empty($field['label']))
                            ? $field['label']
                            : self::getGridFieldLabel($key);

                        $columns[$key] = [
                            'key' => $key,
                            'label' => self::cleanLabel($label),
                        ];
                    }

                    $values[$key] = self::formatGridValue(is_array($field) ? ($field['value'] ?? null) : $field);
                }

                if (!empty($values)) {
                    $rows[] = $values;
                }
            }
        }

        /* column widths (percent) from weights so the table always adds up to 100% */
        $weights = [
            'equipmentName' => 7,
            'instrumentName' => 6,
            'instrumentId' => 8,
            'make' => 5,
            'modelNo' => 6,
            'leastCount' => 5,
            'location' => 10,
            'accuracy' => 5,
            'instrumentRange' => 6,
            'operatingRange' => 6,
            'calibrationTestPoint' => 8,
            'category' => 5,
            'calibrationNextDueDate' => 7,
            'calibrationDoneDate' => 7,
            'remark' => 7,
        ];
        $srWeight = 3;
        $defaultWeight = 6;

        $total = $srWeight;
        foreach ($columns as $key => $column) {
            $total += $weights[$key] ?? $defaultWeight;
        }

        foreach ($columns as $key => &$column) {
            $column['width'] = round((($weights[$key] ?? $defaultWeight) / $total) * 100, 2);
        }
        unset($column);

        return [
            'columns' => array_values($columns),
            'rows' => $rows,
            'sr_width' => round(($srWeight / $total) * 100, 2),
        ];
    }

    /* grid cell value as printable text */
    private static function formatGridValue($value)
    {
        if (is_array($value)) {
            if (empty($value)) {
                return '-';
            }

            $parts = [];
            foreach ($value as $item) {
                $parts[] = is_array($item)
                    ? ($item['name'] ?? $item['file_name'] ?? json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    : (string) $item;
            }

            return implode(', ', $parts);
        }

        if ($value === null || $value === '') {
            return '-';
        }

        return (string) $value;
    }

    /* remove required-field asterisks etc. from labels */
    private static function cleanLabel($label)
    {
        return trim(ltrim((string) $label, '* '));
    }

    /* get attachments by field */
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

    /* known grid field labels, falling back to a formatted key */
    private static function getGridFieldLabel($key)
    {
        $labels = [
            'equipmentName' => 'Equipment Name',
            'instrumentName' => 'Instrument Name',
            'instrumentId' => 'Instrument ID No.',
            'make' => 'Make',
            'modelNo' => 'Sr. No. / Model No.',
            'leastCount' => 'Least Count',
            'location' => 'Location',
            'accuracy' => 'Accuracy',
            'instrumentRange' => 'Instrument Range',
            'operatingRange' => 'Operating Range',
            'calibrationTestPoint' => 'Calibration Test Point',
            'category' => 'Category',
            'calibrationNextDueDate' => 'Calibration Next Due Date',
            'calibrationDoneDate' => 'Calibration Done Date',
            'remark' => 'Remarks',
        ];

        return $labels[$key] ?? self::formatFieldLabel($key);
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

            $value = $field['value'] ?? null;
            $key = $field['key'] ?? '';

            if (str_contains($key, 'attachment')) {
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
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }

        return $result;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);
        $key = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key);

        return ucwords(strtolower(trim($key)));
    }
}