<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\Department;
use App\Models\ProcessRecord;
use App\Models\RecordActivityHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class AddendumFieldInstrumentReportService
{
    /* process_data keys that store a department id (shown as department name in the report) */
    private const DEPARTMENT_ID_KEYS = ['master_department', 'user_dept_selected_department'];

    /* report label overrides (process_data key => label shown in the report, same as the screen) */
    private const FIELD_LABELS = [
        'instrumentType' => 'Instrument Type',
    ];

    /* technical grid keys that must never be shown in the report (compared lowercase) */
    private const GRID_SKIP_KEYS = [
        'row_id',
        'monthlycalibration',
        'calibrationfrequencystartdate',
        'equipmentdbid',
        'isequipmentlocked',
    ];

    /* generate field instrument calibration addendum report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'department', 'initiator', 'gridRecords'])->findOrFail($id);

            $processFields = self::buildProcessFields($record);

            /* initiation date: process data value first, then the record column */
            $initiationDate = self::getProcessFieldValue($processFields, 'dateOfInitiation');
            if (empty($initiationDate) && $record->initiation_date) {
                $initiationDate = Carbon::parse($record->initiation_date)->format('d-M-Y H:i:s');
            }

            $systemFields = self::prepareReportFields([
                ['key' => 'recordNumber', 'label' => 'Record Number', 'value' => self::getProcessFieldValue($processFields, 'recordNumber')],
                ['key' => 'initiator', 'label' => 'Initiator', 'value' => $record->initiator?->name ?? self::getProcessFieldValue($processFields, 'initiator')],
                ['key' => 'dateOfInitiation', 'label' => 'Date of Initiation', 'value' => $initiationDate],
                ['key' => 'initiationDepartment', 'label' => 'Initiation Department', 'value' => $record->department?->name ?? self::getProcessFieldValue($processFields, 'initiationDepartment')],
                ['key' => 'short_description', 'label' => 'Short Description', 'value' => $record->short_description ?: self::getProcessFieldValue($processFields, 'short_description')],
            ]);

            $grid = self::prepareGrid($record->gridRecords);

            /* everything is printed under the General Information tab (no separate review tabs) */
            $tabs = [[
                'tab_title' => 'General Information',
                'sections' => [
                    ['type' => 'fields', 'title' => 'System Information', 'fields' => $systemFields],
                    [
                        'type' => 'fields',
                        'title' => 'Field Instrument Information',
                        'fields' => self::fieldSection($processFields, ['year', 'block', 'area', 'instrumentType', 'master_department']),
                    ],
                    [
                        'type' => 'grid',
                        'title' => 'Field Instrument Calibration Addendum',
                        'columns' => $grid['columns'],
                        'rows' => $grid['rows'],
                        'sr_width' => $grid['sr_width'],
                    ],
                    [
                        'type' => 'fields',
                        'title' => 'Comments & Attachments',
                        'fields' => self::fieldSection($processFields, ['comment', 'attachment']),
                    ],
                    [
                        'type' => 'fields',
                        'title' => 'HOD / Designee Review (Engineering Dept)',
                        'fields' => self::fieldSection($processFields, ['hod_review_comments', 'hod_review_attachment']),
                    ],
                    [
                        'type' => 'fields',
                        'title' => 'User Department Review (User Dept)',
                        'fields' => self::fieldSection($processFields, [
                            'user_dept_selected_department',
                            'user_dept_review_comments',
                            'user_dept_review_attachment',
                        ]),
                    ],
                    [
                        'type' => 'fields',
                        'title' => 'QA Approval Review',
                        'fields' => self::fieldSection($processFields, ['qa_review_comments', 'qa_review_attachment']),
                    ],
                ],
            ]];

            /* cancellation tab only when the record was cancelled */
            $cancellationRemark = self::getProcessFieldValue($processFields, 'cancellation_remark');
            $cancellationAttachment = self::getProcessFieldValue($processFields, 'cancellation_attachment');

            if (!empty($cancellationRemark) || (is_array($cancellationAttachment) && !empty($cancellationAttachment))) {
                $tabs[] = [
                    'tab_title' => 'Cancellation',
                    'sections' => [[
                        'type' => 'fields',
                        'title' => 'Cancellation',
                        'fields' => self::fieldSection($processFields, ['cancellation_remark', 'cancellation_attachment']),
                    ]],
                ];
            }

            $esignHistory = RecordActivityHistory::with([
                'performedBy:id,username',
                'activity:id,name',
                'stage:id,name',
                'targetStage:id,name',
            ])
                ->where('process_record_id', $record->id)
                ->orderBy('performed_at')
                ->get()
                ->map(fn ($item) => [
                    'user_name' => $item->performedBy?->username ?? '-',
                    'activity_name' => $item->activity?->name ?? '-',
                    'from_stage' => $item->stage?->name ?? '-',
                    'to_stage' => $item->targetStage?->name ?? '-',
                    'comment' => $item->comment ?: '-',
                    'performed_at' => $item->performed_at
                        ? Carbon::parse($item->performed_at)->format('d-m-Y H:i:s')
                        : '-',
                ])
                ->toArray();

            $pdf = Pdf::loadView('Reports.User.AddendumFieldInstrumentPlannerReport', [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => 'Calibration Addendum',
                    'document_number' => self::getProcessFieldValue($processFields, 'documentNumber') ?: '-',
                    'document_type' => self::getProcessFieldValue($processFields, 'documentType') ?: '-',
                ],
                'tabs' => $tabs,
                'esign_history' => $esignHistory,
                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ]);

            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('process-record-' . $record->id . '.pdf');
        } catch (\Throwable $e) {
            report($e);

            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* process_data indexed by key: attachments mapped, department ids resolved, labels overridden */
    private static function buildProcessFields(ProcessRecord $record): array
    {
        $recordAttachments = self::decodeArray($record->attachments);
        $processFields = [];

        foreach (self::decodeArray($record->process_data) as $field) {
            $key = is_array($field) ? ($field['key'] ?? null) : null;

            if (!$key) continue;

            $processFields[$key] = [
                'key' => $key,
                'label' => self::FIELD_LABELS[$key] ?? $field['label'] ?? self::formatFieldLabel($key),
                'value' => str_contains($key, 'attachment')
                    ? self::getAttachmentsByField($recordAttachments, $key)
                    : ($field['value'] ?? null),
            ];
        }

        foreach (self::DEPARTMENT_ID_KEYS as $key) {
            if (isset($processFields[$key])) {
                $processFields[$key]['value'] = self::resolveDepartmentNames($processFields[$key]['value']);
            }
        }

        return $processFields;
    }

    /* fields of one report section, in the given order (missing keys are skipped) */
    private static function fieldSection(array $processFields, array $keys): array
    {
        return self::prepareReportFields(self::getFieldsByKeys($processFields, $keys));
    }

    /* build grid columns and rows (one row per instrument) */
    private static function prepareGrid($gridRecords): array
    {
        $columns = [];
        $rows = [];

        foreach ($gridRecords ?? [] as $gridRecord) {
            foreach (self::decodeArray($gridRecord->grid_data) as $row) {
                if (!is_array($row)) continue;

                $values = [];

                foreach ($row as $key => $field) {
                    if (in_array(strtolower((string) $key), self::GRID_SKIP_KEYS, true)) continue;

                    if (!isset($columns[$key])) {
                        $label = (is_array($field) && !empty($field['label']))
                            ? $field['label']
                            : self::getGridFieldLabel($key);

                        $columns[$key] = ['key' => $key, 'label' => self::cleanLabel($label)];
                    }

                    $values[$key] = self::formatGridValue(is_array($field) ? ($field['value'] ?? null) : $field);
                }

                if (!empty($values)) $rows[] = $values;
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
    private static function formatGridValue($value): string
    {
        if (is_array($value)) {
            if (empty($value)) return '-';

            $parts = [];
            foreach ($value as $item) {
                $parts[] = is_array($item)
                    ? ($item['name'] ?? $item['file_name'] ?? json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                    : (string) $item;
            }

            return implode(', ', $parts);
        }

        if ($value === null || $value === '') return '-';

        return (string) $value;
    }

    /* department id(s) -> department name(s); values that are already names are kept */
    private static function resolveDepartmentNames($value): ?string
    {
        $names = [];

        foreach (Arr::wrap($value) as $item) {
            if (is_array($item)) $item = $item['name'] ?? $item['value'] ?? null;
            if ($item === null || $item === '') continue;

            $names[] = is_numeric($item)
                ? (Department::whereKey((int) $item)->value('name') ?? $item)
                : $item;
        }

        return $names ? implode(', ', $names) : null;
    }

    /* remove required-field asterisks etc. from labels */
    private static function cleanLabel($label): string
    {
        return trim(ltrim((string) $label, '* '));
    }

    /* attachments of one attachment field, with absolute urls so they can be opened from the PDF */
    private static function getAttachmentsByField($attachments, string $attachmentField): array
    {
        $result = [];

        foreach (is_array($attachments) ? $attachments : [] as $attachment) {
            if (!is_array($attachment) || ($attachment['attachment_field'] ?? null) !== $attachmentField) continue;

            $url = $attachment['url'] ?? null;

            $result[] = [
                'attachment_field' => $attachmentField,
                'name' => $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => (is_string($url) && $url !== '' && !preg_match('#^https?://#i', $url)) ? url($url) : $url,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
            ];
        }

        return $result;
    }

    /* known grid field labels (used only when the grid data has no label), falling back to a formatted key */
    private static function getGridFieldLabel($key): string
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

    /* decode json string / array safely */
    private static function decodeArray($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);

        return is_array($value) ? $value : [];
    }

    private static function getProcessFieldValue(array $fields, string $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    private static function getFieldsByKeys(array $fields, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) $result[] = $fields[$key];
        }

        return $result;
    }

    /* normalize fields for the view: attachment fields keep their list, everything else becomes printable text */
    private static function prepareReportFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $value = $field['value'] ?? null;
            $key = $field['key'] ?? '';

            if (str_contains($key, 'attachment')) {
                if (!is_array($value) || empty($value)) $value = '-';
            } elseif (is_array($value)) {
                if (isset($value['name'])) {
                    $value = $value['name'];
                } elseif (empty($value)) {
                    $value = '-';
                } else {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            if ($value === null || $value === '') $value = '-';

            $result[] = [
                'key' => $key,
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }

        return $result;
    }

    private static function formatFieldLabel($key): string
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);
        $key = preg_replace('/(?<=[a-z])(?=[A-Z])/', ' ', $key);

        return ucwords(strtolower(trim($key)));
    }
}