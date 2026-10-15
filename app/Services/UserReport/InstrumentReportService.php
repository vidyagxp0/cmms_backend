<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\InstrumentMaster;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use DB;
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
                [
                    'label' => 'Process Name',
                    'value' => $instrument->process?->name ?? '-',
                ],
                [
                    'label' => 'Initiator',
                    'value' => self::getInitiatorName($instrument),
                ],
                [
                    'label' => 'Stage',
                    'value' => $instrument->stage?->name ?? '-',
                ],
                [
                    'label' => 'Created On',
                    'value' => $instrument->created_at
                        ? Carbon::parse($instrument->created_at)->format('d-m-Y H:i:s')
                        : null,
                ],
            ]);

            /* instrument details */
            $specFields = self::prepareReportFields([
                [
                    'label' => 'Instrument Name',
                    'value' => $instrument->instrument_name,
                ],
                [
                    'label' => 'Instrument ID No.',
                    'value' => $instrument->instrument_id_no,
                ],
                [
                    'label' => 'Make',
                    'value' => $instrument->make,
                ],
                [
                    'label' => 'Sr. No. / Model No.',
                    'value' => $instrument->sr_no_model_no,
                ],
                [
                    'label' => 'Location',
                    'value' => $instrument->location,
                ],
                [
                    'label' => 'Category',
                    'value' => $instrument->category,
                ],
                [
                    'label' => 'Least Count',
                    'value' => $instrument->least_count,
                ],
                [
                    'label' => 'Accuracy',
                    'value' => $instrument->accuracy,
                ],
                [
                    'label' => 'Instrument Range',
                    'value' => $instrument->instrument_range,
                ],
                [
                    'label' => 'Operating Range',
                    'value' => $instrument->operating_range,
                ],
                [
                    'label' => 'Calibration Test Points',
                    'value' => $instrument->calibration_test_points,
                ],
            ]);

            /* HOD review */
            $hodFields = self::prepareReportFields([
                [
                    'label' => 'HOD Review Comments',
                    'value' => $instrument->hod_review_comments,
                ],
                [
                    'label' => 'HOD Review Attachment',
                    'value' => self::attachmentLabel(
                        $instrument->hod_review_attachment
                    ),
                ],
            ]);

            /* QA approver review */
            $qaFields = self::prepareReportFields([
                [
                    'label' => 'QA Review Comments',
                    'value' => $instrument->qa_review_comments,
                ],
                [
                    'label' => 'QA Review Attachment',
                    'value' => self::attachmentLabel(
                        $instrument->qa_review_attachment
                    ),
                ],
            ]);

            /* checklist configuration */
            $config = $instrument->checklist_config;

            if (is_string($config)) {
                $config = json_decode($config, true);
            }

            if (!is_array($config)) {
                $config = [];
            }

            $questionColumns = self::cleanColumns(
                $config['question_columns'] ?? []
            );

            $dataColumns = self::cleanColumns(
                $config['data_columns'] ?? []
            );

            $rows = [];
            $hasFrequency = false;

            foreach (($config['questions'] ?? []) as $question) {
                if (!is_array($question)) {
                    continue;
                }

                $values = is_array($question['values'] ?? null)
                    ? $question['values']
                    : [];

                $cells = is_array($question['data_cells'] ?? null)
                    ? $question['data_cells']
                    : [];

                $questionValues = [];
                $hasQuestionText = false;

                foreach ($questionColumns as $column) {
                    $value = $values[$column['id']] ?? '';
                    $value = is_scalar($value)
                        ? trim((string) $value)
                        : '';

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
                    $cellValues[] = self::describeCell(
                        $cells[$column['id']] ?? null
                    );
                }

                $frequencyEnabled =
                    ($question['frequency_enabled'] ?? true) !== false;

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

            $checklistName = trim(
                (string) ($config['checklist_name'] ?? '')
            );

            $checklistSections = [
                [
                    'type' => 'checklist',
                    'title' => $checklistName !== ''
                        ? $checklistName
                        : 'Checklist',
                    'checklist' => [
                        'include_serial_number' =>
                            ($config['include_serial_number'] ?? true) !== false,
                        'question_headers' => array_column(
                            $questionColumns,
                            'column_header'
                        ),
                        'data_headers' => array_column(
                            $dataColumns,
                            'column_header'
                        ),
                        'show_frequency' => $hasFrequency,
                        'rows' => $rows,
                    ],
                ],
            ];

            if (!empty($noteItems)) {
                $checklistSections[] = [
                    'type' => 'notes',
                    'title' => 'Notes',
                    'include_serial_number' =>
                        ($rawNotes['include_serial_number'] ?? true) !== false,
                    'items' => $noteItems,
                ];
            }

            /* report tabs */
            $tabs = [
                [
                    'tab_title' => 'General Information',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'General Information',
                            'fields' => $recordFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'Instrument Details',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'Instrument Specifications',
                            'fields' => $specFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'HOD Review',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'HOD Review Details',
                            'fields' => $hodFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'QA Approver Review',
                    'sections' => [
                        [
                            'type' => 'fields',
                            'title' => 'QA Review Details',
                            'fields' => $qaFields,
                        ],
                    ],
                ],
                [
                    'tab_title' => 'Checklist',
                    'sections' => $checklistSections,
                ],
            ];

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $instrument->process?->name
                        ?? 'Instrument Master',
                    'report_subtitle' => 'Instrument Record Report',
                    'left_logo' => self::logoDataUri(
                        self::LOGO_LEFT_PATHS
                    ),
                    'right_logo' => self::logoDataUri(
                        self::LOGO_RIGHT_PATHS
                    ),
                ],
                'instrument' => [
                    'id' => $instrument->id,
                    'id_no' => $instrument->instrument_id_no,
                ],
                'tabs' => $tabs,
                'footer' => [
                    'generated_by' => auth()->user()?->name ?? '-',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView(
                'Reports.User.InstrumentMasterReport',
                $data
            );

            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream(
                'instrument-master-' . $instrument->id . '.pdf'
            );
        } catch (\Throwable $e) {
            \Log::error('INSTRUMENT REPORT ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error(
                'Failed to generate instrument report.',
                500
            );
        }
    }

    /* resolve the user who created the instrument */
    private static function getInitiatorName($instrument): string
    {
        $audit = DB::table('audits')
            ->where('model', InstrumentMaster::class)
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
    private static function attachmentLabel($attachment): string
    {
        if (!is_string($attachment) || trim($attachment) === '') {
            return '-';
        }

        return basename(trim($attachment));
    }

    /* embed logo as base64 for DomPDF */
    private static function logoDataUri(array $candidates): ?string
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

            $header = trim(
                (string) ($column['column_header'] ?? '')
            );

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

    /* describe checklist field configuration */
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

    /* prepare report fields */
    private static function prepareReportFields(array $fields): array
    {
        $result = [];

        foreach ($fields as $field) {
            $value = $field['value'] ?? null;

            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map(
                    fn ($item) => is_scalar($item)
                        ? trim((string) $item)
                        : '',
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
