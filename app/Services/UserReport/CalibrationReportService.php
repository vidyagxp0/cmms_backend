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
                'gridRecords'
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

            /* get record attachments */
            $recordAttachments = $record->attachments;

            if (is_string($recordAttachments)) {
                $decodedAttachments = json_decode(
                    $recordAttachments,
                    true
                );

                $recordAttachments = is_array($decodedAttachments)
                    ? $decodedAttachments
                    : [];
            }

            if (!is_array($recordAttachments)) {
                $recordAttachments = [];
            }

            /* map record attachments to process fields */
            foreach ($processFields as $key => &$field) {

                if (
                    $key === 'attachment' ||
                    str_contains($key, 'attachment')
                ) {
                    $field['value'] = self::getAttachmentsByField(
                        $recordAttachments,
                        $key
                    );
                }
            }

            unset($field);

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
                    'year',
                    'block',
                    'area',
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

            /* hod review user department */
            $qaReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'user_dept_review_comments',
                    'user_dept_review_attachment',
                ]
            );

            /* QA approval */
            $qaApprovalFields = self::getFieldsByKeys(
                $processFields,
                [
                    'qa_review_comments',
                    'qa_review_attachment',
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

            /* prepare grid data */
            $gridRecords = [];

            foreach ($record->gridRecords ?? [] as $gridRecord) {

                $gridData = $gridRecord->grid_data;

                if (is_string($gridData)) {
                    $gridData = json_decode($gridData, true);
                }

                if (!is_array($gridData)) {
                    continue;
                }

                foreach ($gridData as $row) {

                    if (!is_array($row)) {
                        continue;
                    }

                    $fields = [];

                    foreach ($row as $key => $field) {

                        /*
                        * monthlyCalibration is stored directly as
                        * an array of months, not inside "value".
                        */
                        if ($key === 'monthlyCalibration') {

                            $fields[$key] = [
                                'label' => 'Monthly Calibration',
                                'value' => is_array($field)
                                    ? $field
                                    : [],
                            ];

                            continue;
                        }

                        /*
                        * Normal grid fields
                        */
                        if (is_array($field)) {

                            $fields[$key] = [
                                'label' => self::getGridFieldLabel($key),
                                'value' => $field['value'] ?? null,
                            ];

                        } else {

                            /*
                            * Handle direct values if any field is stored
                            * without label/value structure.
                            */
                            $fields[$key] = [
                                'label' => self::getGridFieldLabel($key),
                                'value' => $field,
                            ];
                        }
                    }

                    if (!empty($fields)) {
                        $gridRecords[] = $fields;
                    }
                }
            }

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
                'tab_title' => 'HOD/Designee Review (Engineering Dept)',
                'sections' => [
                    [
                        'title' => 'HOD/Designee Review (Engineering Dept)',
                        'fields' => $hodReviewFields,
                    ],
                ],
            ];

            /* HOD / Designee Review User Department */
            $qaReviewFields = self::prepareReportFields(
                $qaReviewFields
            );

            $tabs[] = [
                'tab_title' => 'User Department Review (User Dept)',
                'sections' => [
                    [
                        'title' => 'User Department Review (User Dept)',
                        'fields' => $qaReviewFields,
                    ],
                ],
            ];

            /* QA Approver */
            $qaApprovalFields = self::prepareReportFields(
                $qaApprovalFields
            );

            $tabs[] = [
                'tab_title' => 'QA Approval Review',
                'sections' => [
                    [
                        'title' => 'QA Approval Review',
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
                'grid_records' => $gridRecords,

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
            return ResponseHelper::error(
                'Failed to generate process report.',
                500
            );
        }
    }

    /* get attachments by field */
    private static function getAttachmentsByField(
        $attachments,
        $attachmentField
    ) {
        if (!is_array($attachments)) {
            return [];
        }

        $result = [];

        foreach ($attachments as $attachment) {

            if (!is_array($attachment)) {
                continue;
            }

            if (
                ($attachment['attachment_field'] ?? null)
                !== $attachmentField
            ) {
                continue;
            }

            $result[] = [
                'attachment_field' => $attachmentField,
                'name' => $attachment['name']
                    ?? $attachment['file_name']
                    ?? 'Attachment',
                'file_name' => $attachment['file_name']
                    ?? null,
                'path' => $attachment['path']
                    ?? null,
                'url' => $attachment['url']
                    ?? null,
                'mime_type' => $attachment['mime_type']
                    ?? null,
                'size' => $attachment['size']
                    ?? null,
            ];
        }

        return $result;
    }

    /* get grid field label */
    private static function getGridFieldLabel($key)
    {
        $labels = [
            'equipmentInstrumentName' => 'Equipment / Instrument Name',
            'equipmentInstrumentId' => 'Equipment / Instrument ID',
            'department' => 'Department',
            'location' => 'Location',
            'make' => 'Make',
            'model' => 'Model',
            'instrumentrange' => 'Instrument Range',
            'operatingrange' => 'Operating Range',
            'leastCount' => 'Least Count',
            'accuracy' => 'Accuracy',
            'calibrationFrequency' => 'Calibration Frequency',
            'previousCalibrationDate' => 'Previous / Calibration Date',
            'nextCalibrationDate' => 'Next Calibration Date',
            'remark' => 'Remark',
            'monthlyCalibration' => 'Monthly Calibration',
            'calibrationFrequencyStartDate' => 'Calibration Frequency Start Date',
        ];

        return $labels[$key] ?? self::formatFieldLabel($key);
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
            $key = $field['key'] ?? '';

            /* prepare attachment values */
            if (
                $key === 'attachment' ||
                str_contains($key, 'attachment')
            ) {

                if (!is_array($value) || empty($value)) {
                    $value = '-';
                }

            } elseif (is_array($value)) {

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
                'key' => $key,
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