<?php

namespace App\Services\UserReport;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shared helpers used by the UserReport services.
 */
class ReportHelperService
{
    public const COMPANY_NAME = 'Shilpa Medicare Pvt Ltd';

    public const LOGO_LEFT_PATHS = [
        'images/shilpa_logo.png',
        'images/shilpa-logo.png',
        'images/shilpa.png',
    ];

    public const LOGO_RIGHT_PATHS = [
        'images/vidyagxp_logo.png',
        'vidyagxp_logo.png',
        'images/vidyagxp.png',
    ];

    /* ------------------------------------------------------------------ */
    /* generic helpers                                                     */
    /* ------------------------------------------------------------------ */

    /* decode a JSON string (or pass through an array) and always return an array */
    public static function decodeToArray($value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : [];
    }

    /* turn a key such as "siteLocationCode" / "site_location_code" into a readable label */
    public static function formatFieldLabel($key): string
    {
        $key = str_replace(['_', '-'], ' ', (string) $key);
        $key = preg_replace('/([a-z])([A-Z])/', '$1 $2', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    /* does this field key hold attachments */
    public static function isAttachmentKey($key): bool
    {
        return str_contains(strtolower((string) $key), 'attachment');
    }

    /* ------------------------------------------------------------------ */
    /* process record helpers                                              */
    /* ------------------------------------------------------------------ */

    /* convert record->process_data into [key => ['key','label','value']] */
    public static function buildProcessFields($processData): array
    {
        $result = [];

        foreach (self::decodeToArray($processData) as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = $field['key'] ?? null;

            if (!$key) {
                continue;
            }

            $result[$key] = [
                'key' => $key,
                'label' => $field['label'] ?? self::formatFieldLabel($key),
                'value' => $field['value'] ?? null,
            ];
        }

        return $result;
    }

    /* replace the value of every "*attachment*" field with the matching record attachments */
    public static function mapAttachmentsToFields(array $processFields, array $recordAttachments): array
    {
        foreach ($processFields as $key => $field) {
            if (self::isAttachmentKey($key)) {
                $processFields[$key]['value'] = self::getAttachmentsByField($recordAttachments, $key);
            }
        }

        return $processFields;
    }

    /* get attachments belonging to one attachment field */
    public static function getAttachmentsByField($attachments, $attachmentField): array
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

    public static function getProcessFieldValue($fields, $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    public static function getFieldsByKeys($fields, array $keys): array
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    /* prepare process-record fields for the report view (empty values become "-") */
    public static function prepareReportFields($fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $value = $field['value'] ?? null;
            $key = $field['key'] ?? '';

            /* attachments are rendered by Blade */
            if (self::isAttachmentKey($key)) {
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
                'label' => $field['label'] ?? ($key !== '' ? self::formatFieldLabel($key) : '-'),
                'value' => $value,
            ];
        }

        return $result;
    }

    /* ------------------------------------------------------------------ */
    /* instrument master helpers                                           */
    /* ------------------------------------------------------------------ */

    /* prepare simple [label, value] fields (empty values become "-") */
    public static function prepareSimpleFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $value = $field['value'] ?? null;

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(
                    fn ($item) => is_scalar($item) ? trim((string) $item) : '',
                    $value
                )));
            }

            if ($value === null || trim((string) $value) === '') {
                $value = '-';
            }

            $result[] = [
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }

        return $result;
    }

    /* resolve the user who created the instrument (works for any master model) */
    public static function getInitiatorName($instrument): string
    {
        $audit = DB::table('audits')
            ->where('model', get_class($instrument))
            ->where('record_id', $instrument->id)
            ->whereRaw('LOWER(action) = ?', ['created'])
            ->orderBy('id')
            ->first();

        if ($audit && $audit->user_id) {
            $name = User::where('id', $audit->user_id)->value('name');

            if ($name) {
                return $name;
            }
        }

        return '-';
    }

    /* display stored attachment filename */
    public static function attachmentLabel($attachment): string
    {
        if (!is_string($attachment) || trim($attachment) === '') {
            return '-';
        }

        return basename(trim($attachment));
    }

    /* embed logo as base64 for DomPDF */
    public static function logoDataUri(array $candidates): ?string
    {
        foreach ($candidates as $relative) {
            $paths = [
                public_path($relative),
                storage_path('app/public/' . $relative),
            ];

            foreach ($paths as $path) {
                if (!is_file($path)) {
                    continue;
                }

                $content = @file_get_contents($path);

                if ($content === false) {
                    continue;
                }

                $mime = @mime_content_type($path) ?: 'image/png';

                return 'data:' . $mime . ';base64,' . base64_encode($content);
            }
        }

        return null;
    }

    public static function leftLogo(): ?string
    {
        return self::logoDataUri(self::LOGO_LEFT_PATHS);
    }

    public static function rightLogo(): ?string
    {
        return self::logoDataUri(self::LOGO_RIGHT_PATHS);
    }

    /* keep only columns that have a header */
    public static function cleanColumns($columns): array
    {
        if (!is_array($columns)) {
            return [];
        }

        $result = [];

        foreach ($columns as $column) {
            if (!is_array($column)) {
                continue;
            }

            $header = trim((string) ($column['column_header'] ?? ''));

            if ($header === '') {
                continue;
            }

            $result[] = [
                'id' => $column['id'] ?? null,
                'column_header' => $header,
            ];
        }

        return $result;
    }

    /* describe checklist field configuration (field type + options) */
    public static function describeCell($cell): string
    {
        if (!is_array($cell)) {
            return '-';
        }

        $type = $cell['field_type'] ?? 'text';

        $labels = [
            'text' => 'Text',
            'textarea' => 'Text Area',
            'number' => 'Number',
            'date' => 'Date',
            'single_select' => 'Single Select',
            'multi_select' => 'Multi Select',
            'single_select_checkbox' => 'Single Select (Checkbox)',
            'multi_select_checkbox' => 'Multi Select (Checkbox)',
            'checkbox' => 'Checkbox',
        ];

        $label = $labels[$type] ?? Str::headline((string) $type);

        if (
            $type === 'checkbox'
            && ($cell['checkbox_variant'] ?? 'normal') === 'esign'
        ) {
            $esign = $cell['esign_type'] ?? null;

            $label = 'E-Sign'
                . ($esign ? ' (' . Str::headline($esign) . ')' : '');
        }

        $options = [];

        foreach (($cell['options'] ?? []) as $option) {
            $text = is_array($option)
                ? trim((string) ($option['value'] ?? ''))
                : (is_scalar($option) ? trim((string) $option) : '');

            if ($text !== '') {
                $options[] = $text;
            }
        }

        if (!empty($options)) {
            $label .= ': ' . implode(', ', $options);
        }

        if (($cell['required'] ?? false) === true) {
            $label .= ' *';
        }

        return $label;
    }

    /* build the "Checklist" tab sections (checklist table + optional notes) from checklist_config */
    public static function buildChecklistSections($rawConfig): array
    {
        $config = self::decodeToArray($rawConfig);

        $questionColumns = self::cleanColumns($config['question_columns'] ?? []);
        $dataColumns = self::cleanColumns($config['data_columns'] ?? []);

        $rows = [];
        $hasFrequency = false;

        foreach (($config['questions'] ?? []) as $question) {
            if (!is_array($question)) {
                continue;
            }

            $values = is_array($question['values'] ?? null) ? $question['values'] : [];
            $cells = is_array($question['data_cells'] ?? null) ? $question['data_cells'] : [];

            $questionValues = [];
            $hasQuestionText = false;

            foreach ($questionColumns as $column) {
                $value = $values[$column['id']] ?? '';
                $value = is_scalar($value) ? trim((string) $value) : '';

                if ($value !== '') {
                    $hasQuestionText = true;
                }

                $questionValues[] = $value === '' ? '-' : $value;
            }

            /* skip completely empty questions */
            if (!$hasQuestionText) {
                continue;
            }

            $cellValues = [];

            foreach ($dataColumns as $column) {
                $cellValues[] = self::describeCell($cells[$column['id']] ?? null);
            }

            $frequencyEnabled = ($question['frequency_enabled'] ?? true) !== false;

            $frequency = $frequencyEnabled
                ? trim((string) ($question['frequency'] ?? ''))
                : '';

            if ($frequency !== '') {
                $hasFrequency = true;
            }

            $rows[] = [
                'questions' => $questionValues,
                'cells' => $cellValues,
                'frequency' => $frequency === '' ? '-' : $frequency,
            ];
        }

        /* checklist notes */
        $rawNotes = $config['notes'] ?? $config['note'] ?? [];
        $noteItems = [];

        if (is_array($rawNotes)) {
            foreach (($rawNotes['items'] ?? []) as $item) {
                $note = is_array($item)
                    ? trim((string) ($item['value'] ?? ''))
                    : (is_scalar($item) ? trim((string) $item) : '');

                if ($note !== '') {
                    $noteItems[] = $note;
                }
            }
        }

        $checklistName = trim((string) ($config['checklist_name'] ?? ''));

        $sections = [
            [
                'type' => 'checklist',
                'title' => $checklistName !== '' ? $checklistName : 'Checklist',
                'checklist' => [
                    'include_serial_number' => ($config['include_serial_number'] ?? true) !== false,
                    'question_headers' => array_column($questionColumns, 'column_header'),
                    'data_headers' => array_column($dataColumns, 'column_header'),
                    'show_frequency' => $hasFrequency,
                    'rows' => $rows,
                ],
            ],
        ];

        if (!empty($noteItems)) {
            $sections[] = [
                'type' => 'notes',
                'title' => 'Notes',
                'include_serial_number' => ($rawNotes['include_serial_number'] ?? true) !== false,
                'items' => $noteItems,
            ];
        }

        return $sections;
    }
}