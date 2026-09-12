<?php

namespace App\Services\UserReport;

use App\Helpers\ResponseHelper;
use App\Models\ProcessRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PreventiveMaintenancePlannerReportService
{
    /* generate preventive maintenance planner report */
    public static function generateReport($id)
    {
        try {

            /*
             * ---------------------------------------------------------
             * PROCESS RECORD
             * ---------------------------------------------------------
             */

            $record = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
                'gridRecords',
            ])->findOrFail($id);


            /*
             * ---------------------------------------------------------
             * PROCESS DATA
             * ---------------------------------------------------------
             */

            $processData = $record->process_data;

            if (is_string($processData)) {
                $processData = json_decode(
                    $processData,
                    true
                );
            }

            if (!is_array($processData)) {
                $processData = [];
            }


            /*
             * ---------------------------------------------------------
             * PREPARE PROCESS FIELDS
             * ---------------------------------------------------------
             */

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


            /*
             * ---------------------------------------------------------
             * RECORD ATTACHMENTS
             * ---------------------------------------------------------
             */

            $recordAttachments = $record->attachments;

            if (is_string($recordAttachments)) {

                $decodedAttachments = json_decode(
                    $recordAttachments,
                    true
                );

                $recordAttachments = is_array(
                    $decodedAttachments
                )
                    ? $decodedAttachments
                    : [];
            }

            if (!is_array($recordAttachments)) {
                $recordAttachments = [];
            }


            /*
             * Map attachments to their process fields.
             */

            foreach ($processFields as $key => &$field) {

                if (
                    $key === 'attachment' ||
                    str_contains(
                        strtolower($key),
                        'attachment'
                    )
                ) {

                    $field['value'] =
                        self::getAttachmentsByField(
                            $recordAttachments,
                            $key
                        );
                }
            }

            unset($field);


            /*
             * ---------------------------------------------------------
             * GENERAL INFORMATION
             * ---------------------------------------------------------
             */

            $generalFields = self::getFieldsByKeys(
                $processFields,
                [
                    'recordNumber',
                    'siteLocationCode',
                    'initiator',
                    'dateOfInitiation',
                    'initiationDepartment',
                    'short_description',
                ]
            );


            /*
             * If short_description is not present,
             * support shortDescription as well.
             */

            if (
                !isset($processFields['short_description']) &&
                isset($processFields['shortDescription'])
            ) {

                $generalFields = self::getFieldsByKeys(
                    $processFields,
                    [
                        'recordNumber',
                        'siteLocationCode',
                        'initiator',
                        'dateOfInitiation',
                        'initiationDepartment',
                        'shortDescription',
                    ]
                );
            }

            $generalFields = self::prepareReportFields(
                $generalFields
            );


            /*
             * ---------------------------------------------------------
             * PREVENTIVE MAINTENANCE INFORMATION
             * ---------------------------------------------------------
             */

            $preventiveInformationFields =
                self::getFieldsByKeys(
                    $processFields,
                    [
                        'comment',
                        'attachment',
                    ]
                );

            $preventiveInformationFields =
                self::prepareReportFields(
                    $preventiveInformationFields
                );


            /*
             * ---------------------------------------------------------
             * HOD / DESIGNEE REVIEW
             * ---------------------------------------------------------
             */

            $hodReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'hod_review_comments',
                    'hod_review_attachment',
                ]
            );

            $hodReviewFields = self::prepareReportFields(
                $hodReviewFields
            );


            /*
             * ---------------------------------------------------------
             * QA REVIEW
             * ---------------------------------------------------------
             */

            $qaReviewFields = self::getFieldsByKeys(
                $processFields,
                [
                    'user_dept_review_comments',
                    'user_dept_review_attachment',
                ]
            );

            $qaReviewFields = self::prepareReportFields(
                $qaReviewFields
            );


            /*
             * ---------------------------------------------------------
             * QA APPROVAL REVIEW
             * ---------------------------------------------------------
             */

            $qaApprovalFields = self::getFieldsByKeys(
                $processFields,
                [
                    'qa_review_comments',
                    'qa_review_attachment',
                ]
            );

            $qaApprovalFields = self::prepareReportFields(
                $qaApprovalFields
            );


            /*
             * ---------------------------------------------------------
             * CANCELLATION
             * ---------------------------------------------------------
             */

            $cancellationFields = self::getFieldsByKeys(
                $processFields,
                [
                    'cancellation_remark',
                    'cancellation_attachment',
                ]
            );

            $cancellationFields = self::prepareReportFields(
                $cancellationFields
            );


            /*
             * ---------------------------------------------------------
             * GRID DATA
             * ---------------------------------------------------------
             */

            $plannerGrid = self::preparePlannerGrid(
                $record->gridRecords ?? []
            );


            /*
             * ---------------------------------------------------------
             * BASIC RECORD VALUES
             * ---------------------------------------------------------
             */

            $recordNumber = self::getProcessFieldValue(
                $processFields,
                'recordNumber'
            );

            $siteLocationCode = self::getProcessFieldValue(
                $processFields,
                'siteLocationCode'
            );


            /*
             * ---------------------------------------------------------
             * REPORT TABS
             * ---------------------------------------------------------
             */

            $tabs = [];


            /*
             * GENERAL INFORMATION TAB
             */

            $tabs[] = [
                'tab_title' => 'General Information',

                'sections' => [

                    [
                        'title' => 'Process Record Details',
                        'fields' => $generalFields,
                    ],

                    [
                        'title' => 'Preventive Maintenance Information',
                        'fields' => $preventiveInformationFields,
                    ],

                ],
            ];


            /*
             * HOD / DESIGNEE REVIEW
             */

            $tabs[] = [
                'tab_title' => 'HOD / Designee Review',

                'sections' => [

                    [
                        'title' => 'HOD / Designee Review',
                        'fields' => $hodReviewFields,
                    ],

                ],
            ];


            /*
             * QA REVIEW
             */

            $tabs[] = [
                'tab_title' => 'QA Review',

                'sections' => [

                    [
                        'title' => 'QA Review',
                        'fields' => $qaReviewFields,
                    ],

                ],
            ];


            /*
             * QA APPROVAL REVIEW
             */

            $tabs[] = [
                'tab_title' => 'QA Approval Review',

                'sections' => [

                    [
                        'title' => 'QA Approval Review',
                        'fields' => $qaApprovalFields,
                    ],

                ],
            ];


            /*
             * CANCELLATION
             *
             * Only add this tab when cancellation
             * information actually exists.
             */

            if (!empty($cancellationFields)) {

                $tabs[] = [
                    'tab_title' => 'Cancellation',

                    'sections' => [

                        [
                            'title' => 'Cancellation',
                            'fields' => $cancellationFields,
                        ],

                    ],
                ];
            }


            /*
             * ---------------------------------------------------------
             * REPORT DATA
             * ---------------------------------------------------------
             */

            $data = [

                'header' => [

                    'company_name' =>
                        'Shilpa Medicare Pvt Ltd',

                    'report_title' =>
                        $record->process?->name
                        ?? 'Preventive Maintenance Planner',
                ],


                'record' => [

                    'record_id' =>
                        $record->id,

                    'record_number' =>
                        $recordNumber,

                    'site_location_code' =>
                        $siteLocationCode,

                    'process' =>
                        $record->process?->name,

                    'stage' =>
                        $record->stage?->name,

                    'department' =>
                        $record->department?->name,

                    'initiator' =>
                        $record->initiator?->name,

                    'short_description' =>
                        $record->short_description,

                    'initiation_date' =>
                        $record->initiation_date
                            ? Carbon::parse(
                                $record->initiation_date
                            )->format('d-m-Y H:i')
                            : null,
                ],


                /*
                 * Process tabs.
                 */

                'tabs' => $tabs,


                /*
                 * Main Preventive Maintenance Planner grid.
                 */

                'plannerGrid' => $plannerGrid,


                /*
                 * Footer.
                 */

                'footer' => [

                    'generated_by' =>
                        auth()->user()?->name
                        ?? 'System',

                    'generated_at' =>
                        now()->format(
                            'd-m-Y H:i:s'
                        ),
                ],
            ];


            /*
             * ---------------------------------------------------------
             * GENERATE PDF
             * ---------------------------------------------------------
             */

            $pdf = Pdf::loadView(
                'Reports.User.PreventiveMaintenancePlannerReport',
                $data
            );

            /*
             * Planner is a very wide grid.
             * Landscape is required.
             */

            $pdf->setPaper(
                'a4',
                'landscape'
            );


            return $pdf->stream(
                'preventive-maintenance-planner-' .
                $record->id .
                '.pdf'
            );


        } catch (\Exception $e) {

            return ResponseHelper::error(
                'Failed to generate preventive maintenance planner report: ' .
                $e->getMessage(),
                500
            );
        }
    }


    /*
     * -------------------------------------------------------------
     * PREPARE PREVENTIVE MAINTENANCE PLANNER GRID
     * -------------------------------------------------------------
     */

    private static function preparePlannerGrid(
        $gridRecords
    ) {

        $rows = [];


        /*
         * Standard month order.
         */

        $months = [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'May',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Oct',
            'Nov',
            'Dec',
        ];


        foreach ($gridRecords as $gridRecord) {

            $gridData = $gridRecord->grid_data;


            if (is_string($gridData)) {

                $gridData = json_decode(
                    $gridData,
                    true
                );
            }


            if (!is_array($gridData)) {
                continue;
            }


            /*
             * ---------------------------------------------------------
             * CASE 1
             *
             * grid_data is directly:
             *
             * [
             *     row,
             *     row
             * ]
             * ---------------------------------------------------------
             */

            $rawRows = [];


            $isDirectRows = false;

            foreach ($gridData as $item) {

                if (
                    is_array($item) &&
                    (
                        isset($item['equipmentInstrumentName']) ||
                        isset($item['equipmentName']) ||
                        isset($item['equipmentInstrumentId']) ||
                        isset($item['monthlyPreventive'])
                    )
                ) {

                    $isDirectRows = true;
                    break;
                }
            }


            if ($isDirectRows) {

                $rawRows = $gridData;

            } else {

                /*
                 * -----------------------------------------------------
                 * CASE 2
                 *
                 * Support named grid structure.
                 * -----------------------------------------------------
                 */

                foreach ($gridData as $grid) {

                    if (!is_array($grid)) {
                        continue;
                    }


                    $gridName =
                        strtolower(
                            $grid['name'] ?? ''
                        );


                    if (
                        $gridName ===
                        'preventivemaintenanceplanner' ||
                        $gridName ===
                        'preventiveMaintenancePlanner'
                    ) {

                        $gridRows =
                            $grid['rows'] ?? [];


                        if (is_array($gridRows)) {

                            $rawRows =
                                array_merge(
                                    $rawRows,
                                    $gridRows
                                );
                        }
                    }
                }
            }


            /*
             * ---------------------------------------------------------
             * PREPARE EACH EQUIPMENT ROW
             * ---------------------------------------------------------
             */

            foreach ($rawRows as $rowIndex => $row) {

                if (!is_array($row)) {
                    continue;
                }


                $preparedRow = [

                    'row_id' =>
                        $row['row_id']
                        ?? ($rowIndex + 1),

                    'equipment_name' =>
                        self::getGridValue(
                            $row,
                            [
                                'equipmentInstrumentName',
                                'equipmentName',
                            ]
                        ),

                    'equipment_code' =>
                        self::getGridValue(
                            $row,
                            [
                                'equipmentInstrumentId',
                                'equipmentId',
                                'equipmentCode',
                            ]
                        ),

                    'block' =>
                        self::getGridValue(
                            $row,
                            [
                                'block',
                            ]
                        ),

                    'department' =>
                        self::getGridValue(
                            $row,
                            [
                                'department',
                            ]
                        ),

                    'location' =>
                        self::getGridValue(
                            $row,
                            [
                                'location',
                            ]
                        ),

                    'preventive_frequency' =>
                        self::getGridValue(
                            $row,
                            [
                                'preventiveFrequency',
                            ]
                        ),

                    'frequency_start_date' =>
                        self::getGridValue(
                            $row,
                            [
                                'preventiveFrequencyStartDate',
                            ]
                        ),

                    'previous_preventive_date' =>
                        self::getGridValue(
                            $row,
                            [
                                'previousPreventiveDate',
                            ]
                        ),

                    'next_preventive_date' =>
                        self::getGridValue(
                            $row,
                            [
                                'nextPreventiveDate',
                            ]
                        ),

                    'make' =>
                        self::getGridValue(
                            $row,
                            [
                                'make',
                            ]
                        ),

                    'model' =>
                        self::getGridValue(
                            $row,
                            [
                                'model',
                            ]
                        ),

                    'remark' =>
                        self::getGridValue(
                            $row,
                            [
                                'remark',
                            ]
                        ),

                    'monthly' => [],
                ];


                /*
                 * -----------------------------------------------------
                 * MONTHLY PLANNED / ACTUAL DATES
                 * -----------------------------------------------------
                 */

                $monthlyPreventive =
                    self::getRawGridValue(
                        $row,
                        'monthlyPreventive'
                    );


                if (
                    is_string(
                        $monthlyPreventive
                    )
                ) {

                    $decoded =
                        json_decode(
                            $monthlyPreventive,
                            true
                        );

                    $monthlyPreventive =
                        is_array($decoded)
                            ? $decoded
                            : [];
                }


                if (
                    !is_array(
                        $monthlyPreventive
                    )
                ) {

                    $monthlyPreventive = [];
                }


                foreach ($months as $month) {

                    $monthData =
                        $monthlyPreventive[
                            $month
                        ] ?? [];


                    if (!is_array($monthData)) {
                        $monthData = [];
                    }


                    $preparedRow['monthly'][$month] = [

                        'planned_date' =>
                            $monthData[
                                'plannedDate'
                            ] ?? null,

                        'execute_date' =>
                            $monthData[
                                'executeDate'
                            ] ?? null,
                    ];
                }


                $rows[] = $preparedRow;
            }
        }


        return [

            'name' =>
                'preventiveMaintenancePlanner',

            'label' =>
                'Preventive Maintenance Planner',

            'months' =>
                $months,

            'rows' =>
                $rows,
        ];
    }


    /*
     * -------------------------------------------------------------
     * GET GRID VALUE
     * -------------------------------------------------------------
     */

    private static function getGridValue(
        $row,
        array $keys
    ) {

        foreach ($keys as $key) {

            if (!array_key_exists($key, $row)) {
                continue;
            }


            $value = $row[$key];


            if (is_array($value)) {

                return $value['value']
                    ?? null;
            }


            return $value;
        }


        return null;
    }


    /*
     * -------------------------------------------------------------
     * GET RAW GRID VALUE
     * -------------------------------------------------------------
     */

    private static function getRawGridValue(
        $row,
        $key
    ) {

        if (!array_key_exists($key, $row)) {
            return null;
        }


        $value = $row[$key];


        if (
            is_array($value) &&
            array_key_exists('value', $value)
        ) {

            return $value['value'];
        }


        return $value;
    }


    /*
     * -------------------------------------------------------------
     * GET PROCESS FIELD VALUE
     * -------------------------------------------------------------
     */

    private static function getProcessFieldValue(
        $fields,
        $key
    ) {

        if (!isset($fields[$key])) {
            return null;
        }


        return $fields[$key]['value']
            ?? null;
    }


    /*
     * -------------------------------------------------------------
     * GET FIELDS BY KEYS
     * -------------------------------------------------------------
     */

    private static function getFieldsByKeys(
        $fields,
        array $keys
    ) {

        $result = [];


        foreach ($keys as $key) {

            if (!isset($fields[$key])) {
                continue;
            }


            $result[] =
                $fields[$key];
        }


        return $result;
    }


    /*
     * -------------------------------------------------------------
     * PREPARE REPORT FIELDS
     * -------------------------------------------------------------
     */

    private static function prepareReportFields(
        $fields
    ) {

        $result = [];


        foreach ($fields as $field) {

            if (!is_array($field)) {
                continue;
            }


            $key =
                $field['key'] ?? '';


            $value =
                $field['value'] ?? null;


            /*
             * Attachments.
             */

            if (
                $key === 'attachment' ||
                str_contains(
                    strtolower($key),
                    'attachment'
                )
            ) {

                if (
                    !is_array($value) ||
                    empty($value)
                ) {

                    $value = '-';
                }

            }


            /*
             * Other arrays.
             */

            elseif (is_array($value)) {

                if (
                    isset($value['name'])
                ) {

                    $value =
                        $value['name'];

                } elseif (
                    empty($value)
                ) {

                    $value = '-';

                } else {

                    $value = json_encode(
                        $value,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    );
                }
            }


            if (
                $value === null ||
                $value === ''
            ) {

                $value = '-';
            }


            $result[] = [

                'key' =>
                    $key,

                'label' =>
                    $field['label']
                    ?? self::formatFieldLabel(
                        $key
                    ),

                'value' =>
                    $value,
            ];
        }


        return $result;
    }


    /*
     * -------------------------------------------------------------
     * GET ATTACHMENTS BY FIELD
     * -------------------------------------------------------------
     */

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
                ($attachment['attachment_field']
                    ?? null)
                !== $attachmentField
            ) {

                continue;
            }


            $result[] = [

                'attachment_field' =>
                    $attachmentField,

                'name' =>
                    $attachment['name']
                    ??
                    $attachment['file_name']
                    ??
                    'Attachment',

                'file_name' =>
                    $attachment['file_name']
                    ?? null,

                'path' =>
                    $attachment['path']
                    ?? null,

                'url' =>
                    $attachment['url']
                    ?? null,

                'mime_type' =>
                    $attachment['mime_type']
                    ?? null,

                'size' =>
                    $attachment['size']
                    ?? null,
            ];
        }


        return $result;
    }


    /*
     * -------------------------------------------------------------
     * FORMAT FIELD LABEL
     * -------------------------------------------------------------
     */

    private static function formatFieldLabel(
        $key
    ) {

        $key = str_replace(
            ['_', '-'],
            ' ',
            $key
        );


        $key = preg_replace(
            '/([a-z])([A-Z])/',
            '$1 $2',
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