<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Throwable;

class AddendumPreventiveManagementReport
{
    /* Generate preventive maintenance report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with([
                'process', 'stage', 'department', 'initiator', 'gridRecords',
            ])->findOrFail($id);

            $fields = self::mapProcessFields($record->process_data);
            $recordAttachments = self::decodeArray($record->attachments);

            foreach ($fields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachments($recordAttachments, $key, $field['value'] ?? null);
                }
            }
            unset($field);

            $documentNumber = self::fieldValue($fields, 'documentNumber');
            $documentType = self::fieldValue($fields, 'documentType');
            $recordNumber = self::fieldValue($fields, 'recordNumber') ?: $record->id;
            $initiationDate = self::fieldValue($fields, 'dateOfInitiation');

            if (!$initiationDate && $record->initiation_date) {
                $initiationDate = Carbon::parse($record->initiation_date)->format('d-m-Y H:i:s');
            }

            $generalFields = self::prepareFields([
                self::makeField('recordNumber', 'Record Number', $recordNumber),
                self::makeField('process', 'Process', $record->process?->name),
                self::makeField('initiator', 'Initiator', $record->initiator?->name ?: self::fieldValue($fields, 'initiator')),
                self::makeField('initiationDepartment', 'Initiation Department', $record->department?->name ?: self::fieldValue($fields, 'initiationDepartment')),
                self::makeField('dateOfInitiation', 'Date of Initiation', $initiationDate),
                self::makeField('short_description', 'Short Description', $record->short_description ?: self::fieldValue($fields, 'short_description')),
            ]);

            $maintenanceKeys = [
                'equipmentInstrumentName', 'equipmentInstrumentId', 'block', 'department',
                'location', 'previousPreventiveDate', 'nextPreventiveDate',
                'preventive_frequency', 'preventiveFrequency', 'remark',
            ];

            $maintenanceFields = self::prepareFields(self::selectFields($fields, $maintenanceKeys));
            $grid = self::prepareGrid($record->gridRecords);

            $comments = self::prepareFields(self::selectFields($fields, ['comment', 'comments', 'attachment']));
            $reviewRows = [
                self::makeReviewRow($fields, 'engineer_review_comments', 'Engineer Review Comments', 'engineer_review_attachment', 'Engineer Review Attachment'),
                self::makeReviewRow($fields, 'qa_approval_comments', 'QA Review Comments', 'qa_approval_attachment', 'QA Review Attachment'),
            ];
            $reviewRows = array_values(array_filter($reviewRows));

            $cancellationFields = self::prepareFields(self::selectFields($fields, ['cancellation_remark', 'cancellation_attachment']));

            $sections = [
                ['type' => 'fields', 'title' => 'General Information', 'fields' => $generalFields],
                ['type' => 'fields', 'title' => 'Preventive Maintenance Information', 'fields' => $maintenanceFields],
                [
                    'type' => 'grid',
                    'title' => 'Preventive Maintenance Planner',
                    'columns' => $grid['columns'],
                    'rows' => $grid['rows'],
                    'sr_width' => $grid['sr_width'],
                ],
            ];

            if ($comments) {
                $sections[] = ['type' => 'fields', 'title' => 'Comments & Attachments', 'fields' => $comments];
            }

            if ($cancellationFields) {
                $sections[] = ['type' => 'fields', 'title' => 'Cancellation Details', 'fields' => $cancellationFields];
            }

            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Limited',
                    'report_title' => 'Addendum Preventive Maintenance Report',
                    'document_number' => $documentNumber ?: '-',
                    'document_type' => $documentType ?: '-',
                ],
                'tabs' => [['tab_title' => 'Preventive Maintenance', 'sections' => $sections]],
                'reviewRows' => $reviewRows,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView('Reports.User.AddendumPreventiveManagementReport', $data);
            $pdf->setPaper('a4', 'landscape');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            $fileName = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $recordNumber);
            return $pdf->stream('preventive-maintenance-' . $fileName . '.pdf');
        } catch (Throwable $e) {
            report($e);
            return ResponseHelper::error('Failed to generate preventive maintenance report.', 500);
        }
    }

    /* Map process data by key */
    private static function mapProcessFields($value): array
    {
        $result = [];
        foreach (self::decodeArray($value) as $item) {
            if (!is_array($item) || empty($item['key'])) continue;
            $key = (string) $item['key'];
            $result[$key] = [
                'key' => $key,
                'label' => $item['label'] ?? self::label($key),
                'value' => $item['value'] ?? null,
            ];
        }
        return $result;
    }

    /* Prepare grid rows and dynamic columns */
    private static function prepareGrid($gridRecords): array
    {
        $skipKeys = ['row_id', 'equipmentDbId', 'weeklyPreventive', 'monthlyCalibration', 'calibrationFrequencyStartDate'];
        $columns = [];
        $rows = [];

        foreach ($gridRecords ?? [] as $gridRecord) {
            foreach (self::decodeArray($gridRecord->grid_data) as $gridRow) {
                if (!is_array($gridRow)) continue;
                $row = [];

                foreach ($gridRow as $key => $field) {
                    if (in_array($key, $skipKeys, true)) continue;

                    $value = is_array($field) && array_key_exists('value', $field) ? $field['value'] : $field;
                    if (is_array($value)) continue;

                    if (!isset($columns[$key])) {
                        $columns[$key] = [
                            'key' => $key,
                            'label' => self::cleanLabel(is_array($field) ? ($field['label'] ?? self::label($key)) : self::label($key)),
                        ];
                    }
                    $row[$key] = self::formatGridValue($value);
                }

                if ($row) $rows[] = $row;
            }
        }

        $weights = ['equipmentInstrumentName' => 12, 'equipmentInstrumentId' => 10, 'location' => 10, 'remark' => 8];
        $srWeight = 3;
        $defaultWeight = 7;
        $totalWeight = $srWeight;

        foreach ($columns as $key => $column) $totalWeight += $weights[$key] ?? $defaultWeight;
        foreach ($columns as $key => &$column) {
            $column['width'] = round((($weights[$key] ?? $defaultWeight) / $totalWeight) * 100, 2);
        }
        unset($column);

        return [
            'columns' => array_values($columns),
            'rows' => $rows,
            'sr_width' => round(($srWeight / $totalWeight) * 100, 2),
        ];
    }

    /* Build one compact review row */
    private static function makeReviewRow(array $fields, string $commentKey, string $commentLabel, string $attachmentKey, string $attachmentLabel): ?array
    {
        if (!isset($fields[$commentKey]) && !isset($fields[$attachmentKey])) return null;

        return [
            self::makeField($commentKey, $commentLabel, self::normaliseValue($fields[$commentKey]['value'] ?? null)),
            self::makeField($attachmentKey, $attachmentLabel, self::normaliseAttachments($fields[$attachmentKey]['value'] ?? null)),
        ];
    }

    /* Select fields by their process-data keys */
    private static function selectFields(array $fields, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (isset($fields[$key])) $result[] = $fields[$key];
        }
        return $result;
    }

    /* Resolve attachments from process record */
    private static function getAttachments(array $attachments, string $key, $fallback): array
    {
        $result = [];
        foreach ($attachments as $item) {
            if (!is_array($item) || ($item['attachment_field'] ?? null) !== $key) continue;
            $result[] = [
                'name' => $item['name'] ?? $item['file_name'] ?? 'Attachment',
                'file_name' => $item['file_name'] ?? null,
                'path' => $item['path'] ?? null,
                'url' => $item['url'] ?? null,
            ];
        }
        return $result ?: self::normaliseAttachments($fallback);
    }

    /* Normalise attachment values */
    private static function normaliseAttachments($value): array
    {
        if (is_string($value) && $value !== '') return [['name' => basename($value), 'url' => $value]];
        if (!is_array($value) || empty($value)) return [];

        $result = [];
        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $result[] = ['name' => basename($item), 'url' => $item];
            } elseif (is_array($item)) {
                $result[] = [
                    'name' => $item['name'] ?? $item['file_name'] ?? 'Attachment',
                    'file_name' => $item['file_name'] ?? null,
                    'url' => $item['url'] ?? null,
                    'path' => $item['path'] ?? null,
                ];
            }
        }
        return $result;
    }

    /* Prepare printable fields */
    private static function prepareFields(array $fields): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) continue;
            $key = strtolower((string) ($field['key'] ?? ''));
            $value = $field['value'] ?? null;
            $value = str_contains($key, 'attachment')
                ? self::normaliseAttachments($value)
                : self::normaliseValue($value);

            $result[] = [
                'key' => $field['key'] ?? '',
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }
        return $result;
    }

    /* Format values for report output */
    private static function normaliseValue($value)
    {
        if (is_array($value)) {
            if (isset($value['name'])) return $value['name'];
            return empty($value) ? '-' : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $value !== null && $value !== '' ? $value : '-';
    }

    /* Create report field */
    private static function makeField(string $key, string $label, $value): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value];
    }

    /* Read process field value */
    private static function fieldValue(array $fields, string $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    /* Decode JSON values safely */
    private static function decodeArray($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);
        return is_array($value) ? $value : [];
    }

    /* Format grid cell */
    private static function formatGridValue($value): string
    {
        if ($value === null || $value === '') return '-';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return is_scalar($value) ? (string) $value : '-';
    }

    /* Clean report label */
    private static function cleanLabel(string $label): string
    {
        return trim(ltrim($label, '* '));
    }

    /* Convert key to readable label */
    private static function label(string $key): string
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
