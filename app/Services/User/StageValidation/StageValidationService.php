<?php

namespace App\Services\User\StageValidation;

use App\Models\GridRecord;
use App\Models\ProcessRecord;

class StageValidationService
{
    private const BYPASS_VALIDATION_ACTIVITY_IDS = [
        /* cancel activity IDs */
        1, 9, 17, 25, 31, 37, 43, 51, 59, 65, 71, 77, 83, 89, 97,

        /* more info activity IDs */
        6, 7, 8, 14, 15, 16, 22, 23, 24, 29, 30, 35, 36, 41, 42,
        48, 49, 50, 56, 57, 58, 63, 64, 69, 70, 75, 76, 81, 82,
        87, 88, 94, 95, 96, 101, 102,
    ];

    private const RULES = [
        /* Calibration Planner */
        1 => [
            2 => [
                'process_fields' => [
                    'year' => 'Year',
                    'block' => 'Block',
                    'area' => 'Area',
                    'comment' => 'Comments',
                    'instrumentType' => 'Instrument Type',
                ],
                'grid_fields' => [
                    'equipmentInstrumentName' => 'Instrument Name',
                    'plannedDate' => 'Planned Date',
                ],
            ],
            3 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            4 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'User Dept Review Comments',
                ],
            ],
            5 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Calibration Management */
        5 => [
            26 => [
                'process_fields' => [
                    'comments' => 'Comments',
                ],
            ],
            27 => [
                'process_fields' => [
                    'implementorComments' => 'HOD/Designee Comments',
                ],
            ],
            28 => [
                'process_fields' => [
                    'qaReviewComments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Preventive Maintenance Planner */
        2 => [
            10 => [
                'grid_fields' => [
                    'equipmentInstrumentName' => 'Instrument Name',
                ],
            ],
            11 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            12 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'QA Review Comments',
                ],
            ],
            13 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Approver Comments',
                ],
            ],
        ],

        /* Preventive Maintenance */
        4 => [
            18 => [
                'process_fields' => [
                    'equipmentInstrumentName' => 'Equipment Name',
                    'equipmentInstrumentId' => 'Equipment Code',
                    'block' => 'Block',
                    'department' => 'Department',
                    'location' => 'Location',
                    // 'previousPreventiveDate' => 'Previous Preventive Date',
                    'remark' => 'Remark',
                ],
            ],
            19 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'User Department Review Comments',
                ],
            ],
            20 => [
                'process_fields' => [
                    'engineer_review_comments' => 'Engineering Department Review Comments',
                ],
            ],
            21 => [
                'process_fields' => [
                    'qa_approval_comments' => 'QA Approval Comments',
                ],
            ],
        ],

        /* Field Instrument Calibration Addendum */
        8 => [
            52 => [
                'process_fields' => [
                    'year' => 'Year',
                    'block' => 'Block',
                    'area' => 'Area',
                    'comment' => 'Comments',
                ],
                'grid_fields' => [
                    'equipmentName' => 'Equipment Name',
                    'calibrationNextDueDate' => 'Calibration Next Due Date',
                ],
            ],
            53 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            54 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'User Dept Review Comments',
                ],
            ],
            55 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Addendum Calibration Management */
        12 => [
            78 => [
                'process_fields' => [
                    'comments' => 'Comments',
                ],
            ],
            79 => [
                'process_fields' => [
                    'implementorComments' => 'HOD/Designee Comment',
                ],
            ],
            80 => [
                'process_fields' => [
                    'qaReviewComments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Unplanned Calibration Planner */
        14 => [
            90 => [
                'process_fields' => [
                    'year' => 'Year',
                    'block' => 'Block',
                    'area' => 'Area',
                    'comment' => 'Comments',
                    'instrumentType' => 'Instrument Type',
                ],
                'grid_fields' => [
                    'equipmentInstrumentName' => 'Instrument Name',
                    'plannedDate' => 'Planned Date',
                ],
            ],
            91 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            92 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'User Dept Review Comments',
                    'user_dept_selected_department' => 'User Deptartment',
                ],
            ],
            93 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Unplanned Calibration Management */
        15 => [
            98 => [
                'process_fields' => [
                    'comments' => 'Comments',
                ],
            ],
            99 => [
                'process_fields' => [
                    'implementorComments' => 'HOD/Designee Comments',
                ],
            ],
            100 => [
                'process_fields' => [
                    'qaReviewComments' => 'QA Review Comments',
                ],
            ],
        ],
    ];

    public static function validateActivity(
        ProcessRecord $processRecord,
        int $activityId
    ): ?string {

        /* Skip all validations for Cancel and More Info Required */
        if (in_array($activityId, self::BYPASS_VALIDATION_ACTIVITY_IDS, true)) {
            return null;
        }

        $processId = (int) $processRecord->process_id;

        $rules = self::RULES[$processId][$activityId] ?? null;

        if ($rules === null) {
            return null;
        }

        /* Validate process fields */
        $processData = self::normalize($processRecord->process_data);

        foreach ($rules['process_fields'] ?? [] as $key => $label) {
            $value = self::getProcessFieldValue($processData, $key);

            if (!self::hasValue($value)) {
                return "Please enter {$label} before proceeding.";
            }
        }

        /* Validate grid fields */
        if (!empty($rules['grid_fields']) || !empty($rules['week_dates_required'])) {
            $gridRecords = GridRecord::where(
                'process_record_id',
                $processRecord->id
            )->get();

            if ($gridRecords->isEmpty()) {
                return 'Please add at least one grid record before proceeding.';
            }

            $rowNumber = 0;

            foreach ($gridRecords as $gridRecord) {
                $gridData = self::normalize($gridRecord->grid_data);

                foreach ($gridData as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    $rowNumber++;

                    foreach ($rules['grid_fields'] ?? [] as $key => $label) {
                        $value = self::getGridFieldValue($row, $key);

                        if (!self::hasValue($value)) {
                            $instrumentName = self::getGridFieldValue(
                                $row,
                                'equipmentInstrumentName'
                            );

                            $rowLabel = self::hasValue($instrumentName)
                                ? " ({$instrumentName})"
                                : '';

                            return "{$label} is required in grid row {$rowNumber}{$rowLabel}.";
                        }
                    }

                    /* Validate weekly preventive dates */
                    if (!empty($rules['week_dates_required'])) {
                        $weekDates = self::normalize(
                            $gridRecord->week_dates_data
                        );

                        if (empty($weekDates)) {
                            return 'Weekly preventive dates are required in grid row ' . $rowNumber . '.';
                        }

                        $weeklyPreventive = self::getGridFieldValue(
                            $row,
                            'weeklyPreventive'
                        );

                        foreach ([
                            'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
                            'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
                        ] as $month) {
                            if (
                                !isset($weekDates[$month]) ||
                                !is_array($weekDates[$month])
                            ) {
                                return "Weekly preventive dates for {$month} are missing in grid row {$rowNumber}.";
                            }

                            $monthlyPlanned = is_array($weeklyPreventive)
                                ? ($weeklyPreventive[$month] ?? [])
                                : [];

                            foreach ($weekDates[$month] as $weekIndex => $date) {
                                $planned = $monthlyPlanned[$weekIndex]['planned'] ?? null;

                                if (
                                    self::hasValue($planned) &&
                                    !self::hasValue($date)
                                ) {
                                    return "A weekly preventive date is missing for {$month}, grid row {$rowNumber}.";
                                }
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function getProcessFieldValue(
        array $data,
        string $key
    ) {
        foreach ($data as $field) {
            if (!is_array($field)) {
                continue;
            }

            if (($field['key'] ?? null) === $key) {
                return $field['value'] ?? null;
            }
        }

        return null;
    }

    private static function getGridFieldValue(
        array $row,
        string $key
    ) {
        if (!array_key_exists($key, $row)) {
            return null;
        }

        $field = $row[$key];

        if (is_array($field) && array_key_exists('value', $field)) {
            return $field['value'];
        }

        return $field;
    }

    private static function normalize($data): array
    {
        if ($data instanceof \Illuminate\Support\Collection) {
            $data = $data->toArray();
        }

        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($data)) {
            $data = method_exists($data, 'toArray')
                ? $data->toArray()
                : (array) $data;
        }

        return is_array($data) ? $data : [];
    }

    private static function hasValue($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value) && empty($value)) {
            return false;
        }

        return true;
    }
}
