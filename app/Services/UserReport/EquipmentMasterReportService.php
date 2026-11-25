<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\EquipmentMaster;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EquipmentMasterReportService
{
    /* shown in the header row under the title (the field instrument record has no document number / type of its own) */
    private const DEFAULT_DOCUMENT_NUMBER = '-';
    private const DEFAULT_DOCUMENT_TYPE = '-';

    /* generate Equipment Master report */
    public static function generateEquipmentInstrumentReport($id)
    {
        try {
            $instrument = EquipmentMaster::find($id);

            if (!$instrument) {
                return ResponseHelper::error('Equipment Instrument not found.', 404);
            }

            /* general information */
            $recordFields = self::prepareReportFields([
                ['label' => 'Process Name', 'value' => $instrument->process?->name ?? '-'],

                ...(
                    (int) $instrument->is_imported === 0
                    ? [
                        [
                            'label' => 'Initiator',
                            'value' => self::getInitiatorName($instrument),
                        ],
                        [
                            'label' => 'Stage',
                            'value' => $instrument->stage?->name ?? '-',
                        ],
                    ]
                    : []
                ),

                [
                    'label' => 'Created On',
                    'value' => $instrument->created_at
                        ? Carbon::parse($instrument->created_at)->format('d-m-Y H:i:s')
                        : null,
                ],
            ]);

            /* field instrument details */
            $specFields = self::prepareReportFields([
                ['label' => 'Department Name', 'value' => $instrument->department->name],
                ['label' => 'Equipment / Instrument Name', 'value' => $instrument->equipment_name],
                ['label' => 'Equipment / Instrument Code Number', 'value' => $instrument->equipment_id_no],
                ['label' => 'Location', 'value' => $instrument->location],
                ['label' => 'Asset Code.', 'value' => $instrument->asset_code],
                ['label' => 'Make', 'value' => $instrument->make],
                ['label' => 'Model', 'value' => $instrument->sr_no_model_no],
                ['label' => 'Capacity', 'value' => $instrument->capacity],
                
                // ['label' => 'Category', 'value' => $instrument->category],
                // ['label' => 'Accuracy', 'value' => $instrument->accuracy],
                // ['label' => 'Instrument Range', 'value' => $instrument->instrument_range],
                // ['label' => 'Operating Range', 'value' => $instrument->operating_range],
                // ['label' => 'Calibration Test Points', 'value' => $instrument->calibration_test_points],
            ]);

            $hodFields = [];
            $qaFields = [];
            if ((int) $instrument->is_imported === 0) {
                /* HOD review: comments + attachment, printed as one line without a heading */
                $hodFields = self::prepareReportFields([
                    ['label' => 'HOD Review Comments', 'value' => $instrument->hod_review_comments],
                    ['label' => 'HOD Review Attachment', 'value' => self::attachmentLabel($instrument->hod_review_attachment)],
                ]);

                /* QA approver review: comments + attachment, printed as one line without a heading */
                $qaFields = self::prepareReportFields([
                    ['label' => 'QA Review Comments', 'value' => $instrument->qa_review_comments],
                    ['label' => 'QA Review Attachment', 'value' => self::attachmentLabel($instrument->qa_review_attachment)],
                ]);
            }

            /* checklist configuration */
            $config = $instrument->checklist_config;

            if (is_string($config)) {
                $config = json_decode($config, true);
            }

            if (!is_array($config)) {
                $config = [];
            }

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

                    $questionValues[] = $value !== '' ? $value : '-';
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
                    'frequency' => $frequency !== '' ? $frequency : '-',
                ];
            }

            /* checklist notes */
            $rawNotes = $config['notes'] ?? $config['note'] ?? [];
            $noteItems = [];

            if (is_array($rawNotes)) {
                foreach (($rawNotes['items'] ?? []) as $item) {
                    $text = is_array($item)
                        ? trim((string) ($item['value'] ?? ''))
                        : (is_scalar($item) ? trim((string) $item) : '');

                    if ($text !== '') {
                        $noteItems[] = $text;
                    }
                }
            }

            $checklistName = trim((string) ($config['checklist_name'] ?? ''));

            /* report sections, printed in this order (a section without a title has no heading) */
            $sections = [
                [
                    'type' => 'fields',
                    'title' => 'General Information',
                    'fields' => $recordFields,
                ],
                [
                    'type' => 'fields',
                    'title' => 'Field Instrument Details',
                    'fields' => $specFields,
                ],
                [
                    'type' => 'fields',
                    'title' => null,
                    'fields' => array_merge($hodFields, $qaFields),
                ],
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
                    'include_serial_number' => (is_array($rawNotes) ? ($rawNotes['include_serial_number'] ?? true) : true) !== false,
                    'items' => $noteItems,
                ];
            }

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => 'Equipment Master',
                    'document_number' => self::DEFAULT_DOCUMENT_NUMBER,
                    'document_type' => self::DEFAULT_DOCUMENT_TYPE,
                ],
                'instrument' => [
                    'id' => $instrument->id,
                    'id_no' => $instrument->instrument_id_no,
                ],
                'sections' => $sections,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? 'System',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView('Reports.User.EquipmentMasterReport', $data);
            $pdf->setPaper('a4', 'landscape');

            $pdf->render();
            $dompdf   = $pdf->getDomPDF();
            $canvas   = $dompdf->getCanvas();
            $font     = $dompdf->getFontMetrics()->get_font('DejaVu Sans', 'normal');
            $fontBold = $dompdf->getFontMetrics()->get_font('DejaVu Sans', 'bold');
            $w = $canvas->get_width();
            $h = $canvas->get_height();

            $printedBy   = auth()->user()?->name ?? 'System';
            $printedOn   = now()->format('d-m-Y H:i:s');
            $processName = "Equipment Master";

            $canvas->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) use ($font, $fontBold, $w, $h, $printedBy, $printedOn, $processName) {
                $canvas->line(16, $h - 40, $w - 16, $h - 40, [0.83, 0.87, 0.92], 0.8);

                /* process details */
                $canvas->text(16, $h - 32, $processName, $fontBold, 7.5, [0.12, 0.25, 0.4]);
                $canvas->text(16, $h - 22, 'Shilpa Medicare Pvt Ltd | Controlled Document', $font, 7, [0.36, 0.42, 0.49]);

                /* printing details */
                $canvas->text($w - 190, $h - 32, 'Printed By: ' . $printedBy, $font, 7, [0.36, 0.42, 0.49]);
                $canvas->text($w - 190, $h - 22, 'Printed On: ' . $printedOn, $font, 7, [0.36, 0.42, 0.49]);
                $canvas->text($w - 190, $h - 12, "Page $pageNumber of $pageCount", $font, 7, [0.36, 0.42, 0.49]);
            });

            $pdf->setOption('defaultFont', 'DejaVu Sans');

            return $pdf->stream('field-instrument-master-' . $instrument->id . '.pdf');
        } catch (\Throwable $e) {
            return ResponseHelper::error('Failed to generate field instrument report.', 500);
        }
    }

    /* prepare report fields (empty values become "-") */
    private static function prepareReportFields(array $fields): array
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

    /* resolve the user who created the instrument */
    private static function getInitiatorName($instrument): string
    {
        $audit = DB::table('audits')
            ->where('model', EquipmentMaster::class)
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

    /* describe what a configured data cell looks like (field type + options) */
    private static function describeCell($cell): string
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

        if ($type === 'checkbox' && ($cell['checkbox_variant'] ?? 'normal') === 'esign') {
            $esign = $cell['esign_type'] ?? null;
            $label = 'E-Sign' . ($esign ? ' (' . Str::headline($esign) . ')' : '');
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
                'id' => $column['id'] ?? null,
                'column_header' => $header,
            ];
        }

        return $result;
    }

    /* display stored attachment filename */
    private static function attachmentLabel($attachment): string
    {
        if (!is_string($attachment) || trim($attachment) === '') {
            return '-';
        }

        return basename(trim($attachment));
    }
}