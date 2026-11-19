<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\Department;
use App\Models\ProcessRecord;
use App\Models\RecordActivityHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class CalibrationReportService
{
    /* process_data keys that store a department id (shown as department name in the report) */
    private const DEPARTMENT_ID_KEYS = ['user_dept_selected_department'];

    /*
     * Instrument table columns: report column => candidate grid keys (case-insensitive).
     * 'pattern' is a fallback regex on the grid key, 'date' formats the value as dd-mm-yyyy.
     * If a column ever shows "-", add the real grid key name to its 'keys' list.
     */
    private const GRID_COLUMNS = [
        'instrument_name' => ['keys' => ['equipmentInstrumentName', 'instrumentName', 'equipmentName']],
        'instrument_id' => ['keys' => ['equipmentInstrumentId', 'instrumentId', 'instrument_id_no']],
        'make' => ['keys' => ['make']],
        'model' => ['keys' => ['model', 'modelNo']],
        'location' => ['keys' => ['location']],
        'least_count' => ['keys' => ['leastCount', 'least_count']],
        'accuracy' => ['keys' => ['accuracy']],
        'instrument_range' => ['keys' => ['instrumentRange']],
        'operating_range' => ['keys' => ['operatingRange']],
        'test_points' => ['keys' => ['calibrationTestPoints', 'calibrationTestPoint']],
        'category' => ['keys' => ['category']],
        'planned_date' => ['keys' => ['calibrationNextDueDate', 'plannedDate'], 'date' => true],
        'done_date' => ['keys' => ['calibrationDoneDate'], 'date' => true],
        'next_planned_date' => [
            'keys' => [
                'nextCalibrationNextDueDate',
                'nextCalibrationDueDate',
                'nextPlannedDate',
                'nextPlanDate',
                'nextCalibrationDate',
            ],
            'pattern' => '/^next(?!.*done).*(plan|due|date)/i',
            'date' => true,
        ],
        'next_done_date' => [
            'keys' => ['nextCalibrationDoneDate', 'nextDoneDate', 'nextCalibrationDoneOn'],
            'pattern' => '/^next.*done/i',
            'date' => true,
        ],
        'remark' => ['keys' => ['remark', 'remarks']],
    ];

    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'department', 'initiator', 'gridRecords'])->findOrFail($id);

            $processFields = self::buildProcessFields($record);

            $generalFields = self::prepareReportFields([
                ['label' => 'Record Number', 'value' => $processFields['recordNumber']['value'] ?? null],
                ['label' => 'Process', 'value' => $record->process?->name],
                ['label' => 'Initiator', 'value' => $record->initiator?->name],
                ['label' => 'Initiation Department', 'value' => $record->department?->name],
                ['label' => 'Date of Initiation', 'value' => self::formatDate($record->initiation_date)],
                ['label' => 'Short Description', 'value' => $record->short_description],
            ]);

            $calibrationFields = self::prepareReportFields(
                self::getFieldsByKeys($processFields, ['year', 'block', 'area'])
            );

            /* each group is rendered as its own block under the instrument table */
            $reviewGroups = array_values(array_filter([
                self::prepareReportFields(self::getFieldsByKeys($processFields, [
                    'comment',
                    'attachment',
                ])),
                self::prepareReportFields(self::getFieldsByKeys($processFields, [
                    'hod_review_comments',
                    'hod_review_attachment',
                ])),
                self::prepareReportFields(self::getFieldsByKeys($processFields, [
                    'user_dept_selected_department',
                    'user_dept_review_comments',
                    'user_dept_review_attachment',
                ])),
                self::prepareReportFields(self::getFieldsByKeys($processFields, [
                    'qa_review_comments',
                    'qa_review_attachment',
                ])),
            ]));

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

            $pdf = Pdf::loadView('Reports.User.CalibrationSingleReport', [
                'header' => [
                    'company_name' => 'SHILPA MEDICARE LIMITED',
                    'report_title' => $record->process?->name,
                ],
                'record' => [
                    'document_number' => $processFields['documentNumber']['value'] ?? null,
                    'document_type' => $processFields['documentType']['value'] ?? null,
                ],
                'general_fields' => $generalFields,
                'calibration_fields' => $calibrationFields,
                'grid_records' => self::buildInstrumentRows($record->gridRecords),
                'review_groups' => $reviewGroups,
                'esign_history' => $esignHistory,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? '-',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ])->setPaper('a4', 'landscape');

            return $pdf->stream('calibration-planner-' . $record->id . '.pdf');
        } catch (\Throwable $e) {
            report($e);

            return ResponseHelper::error('Failed to generate process report.', 500);
        }
    }

    /* process_data indexed by key, with attachments attached and department ids resolved */
    private static function buildProcessFields(ProcessRecord $record): array
    {
        $recordAttachments = self::decodeArray($record->attachments);
        $processFields = [];

        foreach (self::decodeArray($record->process_data) as $field) {
            $key = is_array($field) ? ($field['key'] ?? null) : null;

            if (!$key) continue;

            $processFields[$key] = [
                'key' => $key,
                'label' => $field['label'] ?? self::formatFieldLabel($key),
                'value' => self::isAttachmentKey($key)
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

    /* one display row per grid row, with fixed report columns */
    private static function buildInstrumentRows($gridRecords): array
    {
        $rows = [];

        foreach ($gridRecords ?? [] as $gridRecord) {
            foreach (self::decodeArray($gridRecord->grid_data) as $row) {
                if (!is_array($row)) continue;

                $values = self::flattenGridRow($row);
                if (empty($values)) continue;

                $mapped = [];

                foreach (self::GRID_COLUMNS as $column => $config) {
                    $value = self::pickGridValue($values, $config['keys'], $config['pattern'] ?? null);

                    if (!empty($config['date'])) $value = self::formatDate($value);

                    $mapped[$column] = ($value === null || $value === '') ? '-' : $value;
                }

                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    /* lowercase key => scalar value for one grid row (nested calibration data is not shown here) */
    private static function flattenGridRow(array $row): array
    {
        $values = [];

        foreach ($row as $key => $field) {
            $lowerKey = strtolower((string) $key);

            if (in_array($lowerKey, ['calibrationfrequencystartdate', 'monthlycalibration'], true)) continue;

            $values[$lowerKey] = self::scalarValue($field);
        }

        return $values;
    }

    /* first non-empty value among the candidate keys, then an optional regex fallback on the key */
    private static function pickGridValue(array $values, array $keys, ?string $pattern = null)
    {
        foreach ($keys as $key) {
            $value = $values[strtolower($key)] ?? null;
            if ($value !== null && $value !== '') return $value;
        }

        if ($pattern) {
            foreach ($values as $key => $value) {
                if ($value !== null && $value !== '' && preg_match($pattern, (string) $key)) return $value;
            }
        }

        return null;
    }

    /* reduce a {value} wrapper / name-object / scalar to a plain scalar */
    private static function scalarValue($field)
    {
        if (is_array($field)) {
            return self::scalarValue($field['value'] ?? $field['name'] ?? null);
        }

        return is_scalar($field) ? $field : null;
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

    /* attachments of one attachment field, with absolute urls so they can be opened from the PDF */
    private static function getAttachmentsByField($attachments, string $attachmentField): array
    {
        $result = [];

        foreach (is_array($attachments) ? $attachments : [] as $attachment) {
            if (!is_array($attachment) || ($attachment['attachment_field'] ?? null) !== $attachmentField) continue;

            $result[] = [
                'name' => $attachment['name'] ?? $attachment['file_name'] ?? 'Attachment',
                'url' => self::resolveAttachmentUrl($attachment['url'] ?? null),
            ];
        }

        return $result;
    }

    private static function resolveAttachmentUrl($url): ?string
    {
        if (!is_string($url) || trim($url) === '') return null;

        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }

    private static function isAttachmentKey($key): bool
    {
        return str_contains((string) $key, 'attachment');
    }

    private static function getFieldsByKeys(array $fields, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) $result[] = $fields[$key];
        }

        return $result;
    }

    /* normalize fields for the view: attachment fields keep name + url, everything else becomes a string */
    private static function prepareReportFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $key = $field['key'] ?? '';
            $value = $field['value'] ?? null;
            $attachments = null;

            if (self::isAttachmentKey($key)) {
                $attachments = array_values(array_filter(is_array($value) ? $value : [], 'is_array'));
                $value = '-';
            } elseif (is_array($value)) {
                $value = isset($value['name'])
                    ? $value['name']
                    : (empty($value) ? '-' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            if ($value === null || $value === '') $value = '-';

            $result[] = [
                'key' => $key,
                'label' => $field['label'] ?? '-',
                'value' => $value,
                'attachments' => $attachments,
            ];
        }

        return $result;
    }

    /* dd-mm-yyyy for ISO dates (yyyy-mm-dd...), anything else is returned unchanged */
    private static function formatDate($value)
    {
        if ($value === null || $value === '') return null;

        if ($value instanceof \DateTimeInterface) return $value->format('d-m-Y');

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return Carbon::createFromFormat('Y-m-d', substr($value, 0, 10))->format('d-m-Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return $value;
    }

    private static function decodeArray($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);

        return is_array($value) ? $value : [];
    }

    private static function formatFieldLabel($key): string
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}