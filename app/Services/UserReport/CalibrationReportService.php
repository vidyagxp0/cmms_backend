<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class CalibrationReportService
{
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
                    'label' => $field['label'] ?? self::formatFieldLabel($key),
                    'value' => $field['value'] ?? null,
                ];
            }

            $recordAttachments = $record->attachments;

            if (is_string($recordAttachments)) {
                $recordAttachments = json_decode($recordAttachments, true);
            }

            if (!is_array($recordAttachments)) {
                $recordAttachments = [];
            }

            foreach ($processFields as $key => &$field) {
                if ($key === 'attachment' || str_contains($key, 'attachment')) {
                    $field['value'] = self::getAttachmentsByField(
                        $recordAttachments,
                        $key
                    );
                }
            }

            unset($field);

            $recordNumber = self::getProcessFieldValue(
                $processFields,
                'recordNumber'
            );

            $documentNumber = self::getProcessFieldValue(
                $processFields,
                'documentNumber'
            );

            $documentType = self::getProcessFieldValue(
                $processFields,
                'documentType'
            );

            $siteLocationCode = self::getProcessFieldValue(
                $processFields,
                'siteLocationCode'
            );

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

            $hodReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'hod_review_comments',
                    'hod_review_attachment',
                ]
            );

            $userDepartmentFields = self::getFieldsByKeys(
                $processFields,
                [
                    'user_dept_review_comments',
                    'user_dept_review_attachment',
                ]
            );

            $qaApprovalFields = self::getFieldsByKeys(
                $processFields,
                [
                    'qa_review_comments',
                    'qa_review_attachment',
                ]
            );

            $generalFields = self::prepareReportFields([
                [
                    'label' => 'Record Number',
                    'value' => $recordNumber,
                ],
                // [
                //     'label' => 'Site / Location Code',
                //     'value' => $siteLocationCode,
                // ],
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
                        ? Carbon::parse($record->initiation_date)->format('d-m-Y')
                        : null,
                ],
                // [
                //     'label' => 'Stage',
                //     'value' => $record->stage?->name,
                // ],
                [
                    'label' => 'Short Description',
                    'value' => $record->short_description,
                ],
            ]);

            $calibrationFields = self::prepareReportFields($calibrationFields);
            $hodReviewFields = self::prepareReportFields($hodReviewFields);
            $userDepartmentFields = self::prepareReportFields($userDepartmentFields);
            $qaApprovalFields = self::prepareReportFields($qaApprovalFields);

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
                        if ($key === 'calibrationFrequencyStartDate') {
                            continue;
                        }

                        if ($key === 'monthlyCalibration') {
                            $fields[$key] = [
                                'label' => 'Monthly Calibration',
                                'value' => is_array($field) ? $field : [],
                            ];

                            continue;
                        }

                        $fields[$key] = [
                            'label' => self::getGridFieldLabel($key),
                            'value' => is_array($field)
                                ? ($field['value'] ?? null)
                                : $field,
                        ];
                    }

                    if (!empty($fields)) {
                        $gridRecords[] = $fields;
                    }
                }
            }

            $data = [
                'header' => [
                    'company_name' => 'SHILPA MEDICARE LIMITED',
                    'report_title' => $record->process->name,
                ],

                'record' => [
                    'record_id' => $record->id,
                    'document_number' => $documentNumber,
                    'document_type' => $documentType,
                    'record_number' => $recordNumber,
                    'site_location_code' => $siteLocationCode,
                    'process' => $record->process?->name,
                    'stage' => $record->stage?->name,
                    'department' => $record->department?->name,
                    'initiator' => $record->initiator?->name,
                    'short_description' => $record->short_description,
                    'initiation_date' => $record->initiation_date
                        ? Carbon::parse($record->initiation_date)->format('d-m-Y')
                        : null,
                ],

                'tabs' => [
                    [
                        'tab_title' => 'General Information',
                        'sections' => [
                            [
                                'title' => 'PROCESS RECORD DETAILS',
                                'fields' => $generalFields,
                            ],
                            [
                                'title' => 'CALIBRATION INFORMATION',
                                'fields' => $calibrationFields,
                            ],
                        ],
                    ],
                    [
                        'tab_title' => 'HOD / DESIGNEE REVIEW',
                        'sections' => [
                            [
                                'title' => 'HOD / DESIGNEE REVIEW',
                                'fields' => $hodReviewFields,
                            ],
                        ],
                    ],
                    [
                        'tab_title' => 'USER DEPARTMENT REVIEW',
                        'sections' => [
                            [
                                'title' => 'USER DEPARTMENT REVIEW',
                                'fields' => $userDepartmentFields,
                            ],
                        ],
                    ],
                    [
                        'tab_title' => 'QA APPROVAL REVIEW',
                        'sections' => [
                            [
                                'title' => 'QA APPROVAL REVIEW',
                                'fields' => $qaApprovalFields,
                            ],
                        ],
                    ],
                ],

                'grid_records' => $gridRecords,

                'footer' => [
                    'generated_by' => auth()->user()?->name ?? '-',
                    'generated_at' => now()->format('d-m-Y H:i:s'),
                ],
            ];

            $pdf = Pdf::loadView(
                'Reports.User.CalibrationSingleReport',
                $data
            );

            $pdf->setPaper('a4', 'landscape');

            return $pdf->stream(
                'calibration-planner-' . $record->id . '.pdf'
            );
        } catch (\Throwable $e) {
            report($e);

            return ResponseHelper::error(
                'Failed to generate process report.',
                500
            );
        }
    }

    private static function getAttachmentsByField($attachments, $attachmentField)
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
                'name' => $attachment['name']
                    ?? $attachment['file_name']
                    ?? 'Attachment',
                'file_name' => $attachment['file_name'] ?? null,
                'path' => $attachment['path'] ?? null,
                'url' => $attachment['url'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'size' => $attachment['size'] ?? null,
            ];
        }

        return $result;
    }

    private static function getGridFieldLabel($key)
    {
        $labels = [
            'equipmentInstrumentName' => 'Instrument Name',
            'equipmentInstrumentId' => 'Instrument ID No.',
            'equipmentName' => 'Equipment Name',
            'instrumentName' => 'Instrument Name',
            'instrumentId' => 'Instrument ID No.',
            'department' => 'Department',
            'location' => 'Location',
            'make' => 'Make',
            'model' => 'Sr. No. / Model No.',
            'modelNo' => 'Sr. No. / Model No.',
            'instrumentrange' => 'Instrument Range',
            'instrumentRange' => 'Instrument Range',
            'operatingrange' => 'Operating Range',
            'operatingRange' => 'Operating Range',
            'leastCount' => 'Least Count',
            'accuracy' => 'Accuracy',
            'calibrationFrequency' => 'Calibration Frequency',
            'previousCalibrationDate' => 'Previous Calibration Date',
            'nextCalibrationDate' => 'Next Calibration Date',
            'calibrationNextDueDate' => 'Planned Date',
            'calibrationDoneDate' => 'Calibration Done On',
            'plannedDate' => 'Planned Date',
            'remark' => 'Remarks',
            'monthlyCalibration' => 'Monthly Calibration',
            'calibrationTestPoints' => 'Calibration Test Points',
            'calibrationTestPoint' => 'Calibration Test Points',
            'category' => 'Category',
        ];

        return $labels[$key] ?? self::formatFieldLabel($key);
    }

    private static function getProcessFieldValue($fields, $key)
    {
        return $fields[$key]['value'] ?? null;
    }

    private static function getFieldsByKeys($fields, array $keys)
    {
        $result = [];

        foreach ($keys as $key) {
            if (isset($fields[$key])) {
                $result[] = $fields[$key];
            }
        }

        return $result;
    }

    private static function prepareReportFields($fields)
    {
        $result = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $value = $field['value'] ?? null;
            $key = $field['key'] ?? '';

            if ($key === 'attachment' || str_contains($key, 'attachment')) {
                if (is_array($value)) {
                    $names = [];

                    foreach ($value as $attachment) {
                        if (is_array($attachment)) {
                            $names[] = $attachment['name']
                                ?? $attachment['file_name']
                                ?? 'Attachment';
                        }
                    }

                    $value = count($names) ? implode(', ', $names) : '-';
                } else {
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
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
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

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }
}