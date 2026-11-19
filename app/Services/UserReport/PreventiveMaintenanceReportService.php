<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ChecklistRecord;
use App\Models\Department;
use App\Models\ProcessRecord;
use App\Models\RecordActivityHistory;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Throwable;

class PreventiveMaintenanceReportService
{
    /*
     * Review lines printed without headings, one table row each.
     * Slot = [label, candidate process_data keys, fallback regex on the key, is a department id].
     * The first key found is used, so small key differences do not hide a field.
     */
    private const REVIEW_LINES = [
        [
            ['User Department', ['user_dept_selected_department'], '/^user.*dep.*select|select.*user.*dep/i', true],
        ],
        [
            ['User Department Review Comments', ['user_dept_review_comments', 'user_department_review_comments'], '/^user.*comment/i', false],
            ['User Department Review Attachment', ['user_dept_review_attachment', 'user_department_review_attachment'], '/^user.*attachment/i', false],
        ],
        [
            ['Engineer Review Comments', ['engineer_review_comments'], '/^eng.*comment/i', false],
            ['Engineer Review Attachment', ['engineer_review_attachment'], '/^eng.*attachment/i', false],
        ],
        [
            ['QA Review Comments', ['qa_approval_comments', 'qa_review_comments'], '/^qa.*comment/i', false],
            ['QA Review Attachment', ['qa_approval_attachment', 'qa_review_attachment'], '/^qa.*attachment/i', false],
        ],
    ];

    /* checklist column widths (weights) by column name */
    private const CHECKLIST_WEIGHTS = ['Check point' => 40, 'Frequency' => 10, 'Remarks' => 20, 'Executed By (Sign & Date)' => 20];

    /* Generate preventive maintenance report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with(['process', 'department', 'initiator'])->findOrFail($id);

            $fields = self::mapProcessFields($record);

            $recordNumber = $fields['recordNumber']['value'] ?? null ?: $record->id;
            $initiationDate = $fields['dateOfInitiation']['value'] ?? null;

            if (!$initiationDate && $record->initiation_date) {
                $initiationDate = Carbon::parse($record->initiation_date)->format('d-m-Y H:i:s');
            }

            $generalFields = self::prepareFields([
                self::makeField('recordNumber', 'Record Number', $recordNumber),
                self::makeField('process', 'Process', $record->process?->name),
                self::makeField('initiator', 'Initiator', $record->initiator?->name ?: ($fields['initiator']['value'] ?? null)),
                self::makeField('initiationDepartment', 'Initiation Department', $record->department?->name ?: ($fields['initiationDepartment']['value'] ?? null)),
                self::makeField('dateOfInitiation', 'Date of Initiation', $initiationDate),
                self::makeField('short_description', 'Short Description', $record->short_description ?: ($fields['short_description']['value'] ?? null)),
            ]);

            /* equipment details in screen order; previous / next dates only when they have a value */
            $equipmentKeys = ['equipmentInstrumentName', 'equipmentInstrumentId', 'block', 'department', 'location'];
            foreach (['previousPreventiveDate', 'nextPreventiveDate'] as $dateKey) {
                if (!empty($fields[$dateKey]['value'])) $equipmentKeys[] = $dateKey;
            }
            $equipmentKeys = array_merge($equipmentKeys, ['preventive_frequency', 'preventiveFrequency', 'remark', 'attachment']);

            $frequency = $fields['preventive_frequency']['value'] ?? $fields['preventiveFrequency']['value'] ?? null;

            /* order: general information, checklist(s), equipment details, comments, reviews, cancellation */
            $sections = array_merge(
                [['type' => 'fields', 'title' => 'General Information', 'fields' => $generalFields]],
                [['type' => 'fields', 'title' => 'Equipment Details', 'fields' => self::prepareFields(self::selectFields($fields, $equipmentKeys))]],
                self::prepareChecklists($record->id, $frequency),
                [['type' => 'fields', 'title' => 'Comments', 'fields' => self::prepareFields(self::selectFields($fields, ['comment', 'comments']))]],
            );

            foreach (self::REVIEW_LINES as $slots) {
                $sections[] = ['type' => 'fields', 'title' => null, 'fields' => self::prepareFields(self::pickReviewFields($fields, $slots))];
            }

            $sections[] = [
                'type' => 'fields',
                'title' => 'Cancellation Details',
                'fields' => self::prepareFields(self::selectFields($fields, ['cancellation_remark', 'cancellation_attachment'])),
            ];

            $esignHistory = RecordActivityHistory::with(['performedBy:id,username', 'activity:id,name', 'stage:id,name', 'targetStage:id,name'])
                ->where('process_record_id', $record->id)
                ->orderBy('performed_at')
                ->get()
                ->map(fn ($item) => [
                    'user_name' => $item->performedBy?->username ?? '-',
                    'activity_name' => $item->activity?->name ?? '-',
                    'from_stage' => $item->stage?->name ?? '-',
                    'to_stage' => $item->targetStage?->name ?? '-',
                    'comment' => $item->comment ?: '-',
                    'performed_at' => $item->performed_at ? Carbon::parse($item->performed_at)->format('d-m-Y H:i:s') : '-',
                ])
                ->toArray();

            $pdf = Pdf::loadView('Reports.User.PreventiveMaintenanceReport', [
                'header' => [
                    'company_name' => 'Shilpa Medicare Limited',
                    'report_title' => $record->process?->name,
                ],
                /* sections without any field / row are dropped here, not in the view */
                'sections' => array_values(array_filter($sections, fn ($s) => !empty($s['fields']) || !empty($s['rows']))),
                'esign_history' => $esignHistory,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ]);

            $pdf->setPaper('a4', 'portrait');
            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('preventive-maintenance-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) $recordNumber) . '.pdf');
        } catch (Throwable $e) {
            report($e);

            return ResponseHelper::error('Failed to generate preventive maintenance report.', 500);
        }
    }

    /* process_data indexed by key; attachment fields get their files from the record attachments */
    private static function mapProcessFields(ProcessRecord $record): array
    {
        $attachments = self::decodeArray($record->attachments);
        $result = [];

        foreach (self::decodeArray($record->process_data) as $item) {
            if (!is_array($item) || empty($item['key'])) continue;

            $key = (string) $item['key'];
            $value = $item['value'] ?? null;

            if (str_contains(strtolower($key), 'attachment')) {
                $value = self::attachmentList($attachments, $key, $value);
            } elseif (in_array($key, ['preventive_frequency', 'preventiveFrequency'], true) && is_string($value)) {
                $value = ucfirst($value);
            }

            $result[$key] = ['key' => $key, 'label' => $item['label'] ?? $key, 'value' => $value];
        }

        return $result;
    }

    /* Checklist sections: one per checklist record, built from checklist_data.questions[].values[] */
    private static function prepareChecklists($processRecordId, ?string $frequency): array
    {
        $sections = [];

        foreach (ChecklistRecord::where('process_record_id', $processRecordId)->get() as $checklist) {
            $data = self::decodeArray($checklist->getAttribute('checklist_data'));
            $rows = [];

            foreach ($data['questions'] ?? [] as $question) {
                $cells = ['Check point' => self::cellText($question['question'] ?? null)];

                /* the frequency of the equipment is printed per row, same as the screen */
                if ($frequency) $cells['Frequency'] = $frequency;

                foreach ($question['values'] ?? [] as $value) {
                    $cells[$value['column_name'] ?? 'Column ' . ($value['column_id'] ?? '')] = self::cellText($value['value'] ?? null);
                }

                $rows[] = $cells;
            }

            if (!$rows) continue;

            $labels = array_values(array_unique(array_merge(...array_map('array_keys', $rows))));
            $showSr = $data['include_serial_number'] ?? true;
            $srWeight = $showSr ? 4 : 0;
            $total = $srWeight + array_sum(array_map(fn ($label) => self::CHECKLIST_WEIGHTS[$label] ?? 12, $labels));

            $sections[] = [
                'type' => 'checklist',
                'title' => $data['checklist_name'] ?? 'Checklist',
                'show_sr' => $showSr,
                'sr_width' => round($srWeight / $total * 100, 2),
                'columns' => array_map(fn ($label) => [
                    'label' => $label,
                    'width' => round((self::CHECKLIST_WEIGHTS[$label] ?? 12) / $total * 100, 2),
                ], $labels),
                'rows' => $rows,
            ];
        }

        return $sections;
    }

    /* checklist cell text: signature block -> Signed / name / date, everything else as plain text */
    private static function cellText($value): string
    {
        if (is_array($value)) {
            if (array_key_exists('checked', $value)) {
                return !empty($value['checked'])
                    ? implode("\n", array_filter(['Signed', $value['signed_by'] ?? null, $value['signed_at'] ?? null]))
                    : '-';
            }

            $lines = [];
            foreach ($value as $key => $item) {
                if (is_scalar($item) && $item !== '') $lines[] = (is_string($key) ? $key . ' : ' : '• ') . $item;
            }

            return $lines ? implode("\n", $lines) : '-';
        }

        return $value === null || $value === '' ? '-' : (string) $value;
    }

    /* Fields of one review line; a department id is shown as the department name */
    private static function pickReviewFields(array $fields, array $slots): array
    {
        $result = [];

        foreach ($slots as [$label, $candidates, $pattern, $isDepartment]) {
            $key = collect($candidates)->first(fn ($candidate) => isset($fields[$candidate]))
                ?? collect(array_keys($fields))->first(fn ($fieldKey) => preg_match($pattern, (string) $fieldKey));

            if ($key === null) continue;

            $value = $fields[$key]['value'] ?? null;

            if ($isDepartment && is_numeric($value)) {
                $value = Department::whereKey((int) $value)->value('name') ?? $value;
            }

            $result[] = self::makeField((string) $key, $label, $value);
        }

        return $result;
    }

    /* attachments of one attachment field (record attachments first, then the field's own value) */
    private static function attachmentList(array $recordAttachments, string $key, $fallback): array
    {
        $items = array_filter($recordAttachments, fn ($item) => is_array($item) && ($item['attachment_field'] ?? null) === $key);

        if (!$items) $items = array_filter(is_array($fallback) ? $fallback : [], 'is_array');

        return array_values(array_map(fn ($item) => [
            'name' => $item['name'] ?? $item['file_name'] ?? 'Attachment',
            'url' => self::resolveUrl($item['url'] ?? null),
        ], $items));
    }

    /* relative paths become absolute urls so the links work from the PDF */
    private static function resolveUrl($url): ?string
    {
        if (!is_string($url) || trim($url) === '') return null;

        $url = trim($url);

        return preg_match('#^https?://#i', $url) ? $url : url($url);
    }

    private static function selectFields(array $fields, array $keys): array
    {
        return array_values(array_filter(array_map(fn ($key) => $fields[$key] ?? null, $keys)));
    }

    /* printable fields: attachment fields keep their file list, everything else becomes text */
    private static function prepareFields(array $fields): array
    {
        return array_map(function ($field) {
            $value = $field['value'] ?? null;

            if (!str_contains(strtolower((string) $field['key']), 'attachment')) {
                $value = is_array($value)
                    ? ($value['name'] ?? ($value ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '-'))
                    : ($value !== null && $value !== '' ? $value : '-');
            }

            return self::makeField($field['key'], $field['label'] ?? '-', $value);
        }, $fields);
    }

    private static function makeField(string $key, string $label, $value): array
    {
        return ['key' => $key, 'label' => $label, 'value' => $value];
    }

    /* decode JSON values safely */
    private static function decodeArray($value): array
    {
        if (is_string($value)) $value = json_decode($value, true);

        return is_array($value) ? $value : [];
    }
}