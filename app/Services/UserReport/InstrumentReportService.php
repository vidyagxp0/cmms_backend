<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\InstrumentMaster;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Str;

class InstrumentReportService
{
    private const LOGO_LEFT_PATHS = [
        'images/shilpa_logo.png',
        'images/shilpa-logo.png',
        'images/shilpa.png',
    ];

    private const LOGO_RIGHT_PATHS = [
        'images/vidyagxp_logo.png',
        'vidyagxp_logo.png',
        'images/vidyagxp.png',
    ];

    /* generate instrument master report */
    public static function generateReport($id)
    {
        try {
            $instrument = InstrumentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error('Instrument not found.', 404);
            }

            /* general information */
            $recordFields = self::prepareReportFields([
                ['label' => 'Process',           'value' => $instrument->process?->name ?? 'Master Instrument'],
                ['label' => 'Stage',             'value' => $instrument->stage?->name],
                ['label' => 'Instrument ID No.', 'value' => $instrument->instrument_id_no],
                ['label' => 'Instrument Name',   'value' => $instrument->instrument_name],
                ['label' => 'Created On',        'value' => $instrument->created_at
                    ? Carbon::parse($instrument->created_at)->format('d-m-Y')
                    : null],
            ]);

            $specFields = self::prepareReportFields([
                ['label' => 'Make',                    'value' => $instrument->make],
                ['label' => 'Sr. No. / Model No.',     'value' => $instrument->sr_no_model_no],
                ['label' => 'Location',                'value' => $instrument->location],
                ['label' => 'Category',                'value' => $instrument->category],
                ['label' => 'Least Count',             'value' => $instrument->least_count],
                ['label' => 'Accuracy',                'value' => $instrument->accuracy],
                ['label' => 'Instrument Range',        'value' => $instrument->instrument_range],
                ['label' => 'Operating Range',         'value' => $instrument->operating_range],
                ['label' => 'Calibration Test Points', 'value' => $instrument->calibration_test_points],
            ]);

            /* checklist configuration */
            $config = $instrument->checklist_config;
            if (is_string($config)) {
                $config = json_decode($config, true);
            }
            if (!is_array($config)) {
                $config = [];
            }

            $questionColumns = self::cleanColumns($config['question_columns'] ?? []);
            $dataColumns     = self::cleanColumns($config['data_columns'] ?? []);

            $rows = [];
            $hasFrequency = false;

            foreach (($config['questions'] ?? []) as $question) {
                if (!is_array($question)) {
                    continue;
                }

                $values = is_array($question['values'] ?? null) ? $question['values'] : [];
                $cells  = is_array($question['data_cells'] ?? null) ? $question['data_cells'] : [];

                $questionValues = [];
                $hasAnyQuestionText = false;
                foreach ($questionColumns as $col) {
                    $v = $values[$col['id']] ?? '';
                    $v = is_scalar($v) ? trim((string) $v) : '';
                    if ($v !== '') {
                        $hasAnyQuestionText = true;
                    }
                    $questionValues[] = $v === '' ? '-' : $v;
                }

                /* skip completely empty rows */
                if (!$hasAnyQuestionText) {
                    continue;
                }

                $cellValues = [];
                foreach ($dataColumns as $col) {
                    $cellValues[] = self::describeCell($cells[$col['id']] ?? null);
                }

                $frequencyEnabled = ($question['frequency_enabled'] ?? true) !== false;
                $frequency = $frequencyEnabled ? trim((string) ($question['frequency'] ?? '')) : '';
                if ($frequency !== '') {
                    $hasFrequency = true;
                }

                $rows[] = [
                    'questions' => $questionValues,
                    'cells'     => $cellValues,
                    'frequency' => $frequency === '' ? '-' : $frequency,
                ];
            }

            $rawNotes = $config['notes'] ?? $config['note'] ?? [];
            $noteItems = [];
            foreach (($rawNotes['items'] ?? []) as $item) {
                $text = is_array($item) ? trim((string) ($item['value'] ?? '')) : trim((string) $item);
                if ($text !== '') {
                    $noteItems[] = $text;
                }
            }

            $checklistName = trim((string) ($config['checklist_name'] ?? ''));

            $checklistSections = [
                [
                    'type'  => 'checklist',
                    'title' => $checklistName !== '' ? $checklistName : 'Checklist',
                    'checklist' => [
                        'include_serial_number' => ($config['include_serial_number'] ?? true) !== false,
                        'question_headers'      => array_column($questionColumns, 'column_header'),
                        'data_headers'          => array_column($dataColumns, 'column_header'),
                        'show_frequency'        => $hasFrequency,
                        'rows'                  => $rows,
                    ],
                ],
            ];

            if (count($noteItems) > 0) {
                $checklistSections[] = [
                    'type'                  => 'notes',
                    'title'                 => 'Notes',
                    'include_serial_number' => ($rawNotes['include_serial_number'] ?? true) !== false,
                    'items'                 => $noteItems,
                ];
            }

            $tabs = [
                [
                    'tab_title' => 'General Information',
                    'sections'  => [
                        ['type' => 'fields', 'title' => 'Instrument Record Detail', 'fields' => $recordFields],
                        ['type' => 'fields', 'title' => 'Instrument Specifications', 'fields' => $specFields],
                    ],
                ],
                [
                    'tab_title' => 'Checklist Configuration',
                    'sections'  => $checklistSections,
                ],
            ];

            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $instrument->process?->name ?? 'Master Instrument',
                    'report_subtitle' => 'Instrument Record Report',
                    'left_logo'    => self::logoDataUri(self::LOGO_LEFT_PATHS),
                    'right_logo'   => self::logoDataUri(self::LOGO_RIGHT_PATHS),
                ],
                'instrument' => [
                    'id'    => $instrument->id,
                    'id_no' => $instrument->instrument_id_no,
                ],
                'tabs'   => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView('Reports.User.InstrumentMasterReport', $data);
            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream('instrument-master-' . $instrument->id . '.pdf');
        } catch (\Exception $e) {
            \Log::error('INSTRUMENT REPORT ERROR', [
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ]);

            return ResponseHelper::error('Failed to generate instrument report.', 500);
        }
    }

    /* embed a logo as base64 so DomPDF never needs remote access */
    private static function logoDataUri(array $candidates): ?string
    {
        foreach ($candidates as $relative) {
            foreach ([public_path($relative), storage_path('app/public/' . $relative)] as $path) {
                if (is_file($path)) {
                    $mime = @mime_content_type($path) ?: 'image/png';

                    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
                }
            }
        }

        return null;
    }

    /* keep only columns that have a header */
    private static function cleanColumns($columns): array
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
                'id'            => $column['id'] ?? null,
                'column_header' => $header,
            ];
        }

        return $result;
    }

    /* describe what a configured data cell looks like (field type + options) */
    private static function describeCell($cell): string
    {
        if (!is_array($cell)) {
            return '-';
        }

        $type = $cell['field_type'] ?? 'text';

        $labels = [
            'text'                   => 'Text',
            'textarea'               => 'Text Area',
            'number'                 => 'Number',
            'date'                   => 'Date',
            'single_select'          => 'Single Select',
            'multi_select'           => 'Multi Select',
            'single_select_checkbox' => 'Single Select (Checkbox)',
            'multi_select_checkbox'  => 'Multi Select (Checkbox)',
            'checkbox'               => 'Checkbox',
        ];

        $label = $labels[$type] ?? Str::headline((string) $type);

        if ($type === 'checkbox' && ($cell['checkbox_variant'] ?? 'normal') === 'esign') {
            $esign = $cell['esign_type'] ?? null;
            $label = 'E-Sign' . ($esign ? ' (' . Str::headline($esign) . ')' : '');
        }

        $options = [];
        foreach (($cell['options'] ?? []) as $option) {
            $text = is_array($option) ? trim((string) ($option['value'] ?? '')) : trim((string) $option);
            if ($text !== '') {
                $options[] = $text;
            }
        }
        if ($options) {
            $label .= ': ' . implode(', ', $options);
        }

        if (($cell['required'] ?? false) === true) {
            $label .= ' *';
        }

        return $label;
    }

    /* prepare report fields (empty values become "-") */
    private static function prepareReportFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $value = $field['value'] ?? null;

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(
                    fn ($v) => is_scalar($v) ? trim((string) $v) : '',
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
}