<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Throwable;

class PreventiveMaintenanceReportService
{
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->findOrFail($id);
            $raw = $record->process_data;
            $raw = is_string($raw) ? json_decode($raw, true) : $raw;
            $raw = is_array($raw) ? $raw : [];

            $fields = [];
            foreach ($raw as $item) {
                if (!is_array($item) || empty($item['key'])) continue;
                $key = $item['key'];
                $fields[$key] = [
                    'key' => $key,
                    'label' => $item['label'] ?? self::label($key),
                    'value' => $item['value'] ?? null,
                ];
            }

            $recordAttachments = $record->attachments;
            $recordAttachments = is_string($recordAttachments) ? json_decode($recordAttachments, true) : $recordAttachments;
            $recordAttachments = is_array($recordAttachments) ? $recordAttachments : [];

            foreach ($fields as $key => &$field) {
                if (str_contains(strtolower($key), 'attachment')) {
                    $field['value'] = self::getAttachments($recordAttachments, $key, $field['value']);
                }
            }
            unset($field);

            $general = self::selectFields($fields, [
                'recordNumber', 'siteLocationCode', 'initiator', 'dateOfInitiation',
                'initiationDepartment', 'short_description', 'shortDescription',
            ]);
            $maintenance = self::selectFields($fields, [
                'equipmentInstrumentName', 'equipmentInstrumentId', 'block', 'department',
                'location', 'previousPreventiveDate', 'nextPreventiveDate',
                'preventive_frequency', 'remark', 'attachment',
            ]);
            $engineer = self::selectFields($fields, ['engineer_review_comments', 'engineer_review_attachment']);
            $qa = self::selectFields($fields, ['qa_approval_comments', 'qa_approval_attachment']);
            $cancellation = self::selectFields($fields, ['cancellation_remark', 'cancellation_attachment']);

            $tabs = [
                ['tab_title' => 'General Information', 'sections' => [
                    ['title' => 'Process Record Details', 'fields' => $general],
                    ['title' => 'Preventive Maintenance Information', 'fields' => $maintenance],
                ]],
                ['tab_title' => 'Engineer Department Review', 'sections' => [
                    ['title' => 'Review By Engineer Department', 'fields' => $engineer],
                ]],
                ['tab_title' => 'QA Approval Review', 'sections' => [
                    ['title' => 'QA Approval', 'fields' => $qa],
                ]],
                ['tab_title' => 'Cancellation', 'sections' => [
                    ['title' => 'Cancellation Details', 'fields' => $cancellation],
                ]],
            ];

            $recordNumber = $fields['recordNumber']['value'] ?? $record->id;
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name ?? 'Preventive Maintenance Report',
                ],
                'record' => [
                    'record_id' => $record->id,
                    'record_number' => $recordNumber,
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => $record->initiation_date
                        ? Carbon::parse($record->initiation_date)->format('d-m-Y H:i') : null,
                ],
                'tabs' => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView('Reports.User.PreventiveMaintenanceReport', $data);
            $pdf->setPaper('a4', 'portrait');
            $fileName = preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $recordNumber);
            return $pdf->stream('preventive-maintenance-' . $fileName . '.pdf');
        } catch (Throwable $e) {
            report($e);
            return ResponseHelper::error('Failed to generate preventive maintenance report.', 500);
        }
    }

    private static function selectFields(array $fields, array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $fields)) continue;
            $field = $fields[$key];
            $value = $field['value'] ?? null;
            if (str_contains(strtolower($key), 'attachment')) {
                $value = is_array($value) ? $value : [];
            } elseif (is_array($value)) {
                $value = isset($value['name']) ? $value['name'] : (empty($value) ? null : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }
            $result[] = ['key' => $key, 'label' => $field['label'] ?? self::label($key), 'value' => $value];
        }
        return $result;
    }

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
        if (!$result && is_array($fallback)) {
            foreach ($fallback as $item) {
                if (is_string($item)) $result[] = ['name' => basename($item), 'url' => $item];
                elseif (is_array($item)) $result[] = [
                    'name' => $item['name'] ?? $item['file_name'] ?? 'Attachment',
                    'file_name' => $item['file_name'] ?? null,
                    'path' => $item['path'] ?? null,
                    'url' => $item['url'] ?? null,
                ];
            }
        }
        return $result;
    }

    private static function label(string $key): string
    {
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
