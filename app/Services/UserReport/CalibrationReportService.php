<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;

class CalibrationReportService
{
    /* generate calibration report */
    public static function generateReport($id)
    {
        try {
            $record = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* process data */
            $processData = $record->process_data;

            if (is_string($processData)) {
                $processData = json_decode($processData, true);
            }

            if (!is_array($processData)) {
                $processData = [];
            }

            $processFields = [];

            foreach ($processData as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? null;

                if (!$key) {
                    continue;
                }

                $processFields[$key] = [
                    'key' => $key,
                    'label' => $field['label']
                        ?? self::formatFieldLabel($key),
                    'value' => $field['value'] ?? null,
                ];
            }

            /* record fields */
            $recordNumber = self::getProcessFieldValue(
                $processFields,
                'recordNumber'
            );

            $siteLocationCode = self::getProcessFieldValue(
                $processFields,
                'siteLocationCode'
            );

            /* calibration fields */
            $calibrationFields = self::getFieldsByKeys(
                $processFields,
                [
                    'hod',
                    'qa_reviewer',
                    'qa_approval',
                    'comment',
                    'attachment',
                ]
            );

            /* HOD review */
            $hodReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'hod_review_comments',
                    'hod_review_attachment',
                ]
            );

            /* QA review */
            $qaReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'qa_review_comments',
                    'qa_review_attachment',
                ]
            );

            /* QA approval */
            $qaApprovalFields = self::getFieldsByKeys(
                $processFields,
                [
                    'qa_approval_comments',
                    'qa_approval_attachment',
                ]
            );

            /* report tabs */
            $tabs = [];

            /* General Information */
            $generalFields = [
                [
                    'label' => 'Record Number',
                    'value' => $recordNumber,
                ],
                [
                    'label' => 'Site / Location Code',
                    'value' => $siteLocationCode,
                ],
                [
                    'label' => 'Process',
                    'value' => $record->process?->name,
                ],
                [
                    'label' => 'Initiator',
                    'value' => $record->initiator?->name,
                ],
                [
                    'label' => 'Initiation Department',
                    'value' => $record->department?->name,
                ],
                [
                    'label' => 'Date of Initiation',
                    'value' => $record->initiation_date
                        ? \Carbon\Carbon::parse(
                            $record->initiation_date
                        )->format('d-m-Y')
                        : null,
                ],
                [
                    'label' => 'Stage',
                    'value' => $record->stage?->name,
                ],
                [
                    'label' => 'Short Description',
                    'value' => $record->short_description,
                ],
            ];

            $generalFields = self::prepareReportFields(
                $generalFields
            );

            $calibrationFields = self::prepareReportFields(
                $calibrationFields
            );

            $tabs[] = [
                'tab_title' => 'General Information',
                'sections' => [
                    [
                        'title' => 'Process Record Detail',
                        'fields' => $generalFields,
                    ],
                    [
                        'title' => 'Calibration Information',
                        'fields' => $calibrationFields,
                    ],
                ],
            ];

            /* HOD / Designee Review */
            $hodReviewFields = self::prepareReportFields(
                $hodReviewFields
            );

            $tabs[] = [
                'tab_title' => 'HOD/Designee Review',
                'sections' => [
                    [
                        'title' => 'HOD/Designee Review',
                        'fields' => $hodReviewFields,
                    ],
                ],
            ];

            /* QA Reviewer */
            $qaReviewFields = self::prepareReportFields(
                $qaReviewFields
            );

            $tabs[] = [
                'tab_title' => 'QA Reviewer',
                'sections' => [
                    [
                        'title' => 'QA Reviewer',
                        'fields' => $qaReviewFields,
                    ],
                ],
            ];

            /* QA Approver */
            $qaApprovalFields = self::prepareReportFields(
                $qaApprovalFields
            );

            $tabs[] = [
                'tab_title' => 'QA Approver',
                'sections' => [
                    [
                        'title' => 'QA Approval',
                        'fields' => $qaApprovalFields,
                    ],
                ],
            ];

            /* report data */
            $data = [
                'header' => [
                    'company_name' => 'Shilpa Medicare Pvt Ltd',
                    'report_title' => $record->process?->name
                        ?? 'Process Report',
                ],

                'record' => [
                    'record_id' => $record->id,
                    'record_number' => $recordNumber,
                    'site_location_code' => $siteLocationCode,
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => $record->initiation_date
                        ? \Carbon\Carbon::parse(
                            $record->initiation_date
                        )->format('d-m-Y')
                        : null,
                ],

                'tabs' => $tabs,

                'footer' => [
                    'generated_by' => auth()->user()?->name,
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];
            
            /* generate PDF */
            $pdf = Pdf::loadView(
                'Reports.User.CalibrationSingleReport',
                $data
            );

            $pdf->setPaper('a4', 'portrait');

            return $pdf->stream(
                'process-record-' . $record->id . '.pdf'
            );

        } catch (\Exception $e) {
            \Log::error(
                'CALIBRATION SINGLE REPORT ERROR',
                [
                    'record_id' => $id,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            return ResponseHelper::error(
                'Failed to generate process report.',
                500
            );
        }
    }

    /* get process field value */
    private static function getProcessFieldValue($fields, $key)
    {
        if (!isset($fields[$key])) {
            return null;
        }

        return $fields[$key]['value'] ?? null;
    }

    /* get fields by keys */
    private static function getFieldsByKeys($fields, array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            if (!isset($fields[$key])) {
                continue;
            }

            $result[] = $fields[$key];
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

            if (is_array($value)) {
                if (isset($value['name'])) {
                    $value = $value['name'];
                } elseif (empty($value)) {
                    $value = '-';
                } else {
                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );
                }
            }

            if ($value === null || $value === '') {
                $value = '-';
            }

            $result[] = [
                'label' => $field['label'] ?? '-',
                'value' => $value,
            ];
        }

        return $result;
    }

    /* format field label */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(
            ['_', '-'],
            ' ',
            $key
        );

        $key = str_replace(
            '/',
            ' / ',
            $key
        );

        return ucwords(
            strtolower(
                trim($key)
            )
        );
    }
}