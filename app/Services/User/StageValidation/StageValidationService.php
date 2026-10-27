<?php

namespace App\Services\User\StageValidation;

use App\Models\GridRecord;
use App\Models\ProcessRecord;

class StageValidationService
{
    private const RULES = [
        /* Calibration Planner */
        1 => [
            1 => [
                'process_fields' => [
                    'year' => 'Year',
                    'block' => 'Block',
                    'area' => 'Area',
                    'comment' => 'Comments',
                ],
                'grid_fields' => [
                    'equipmentInstrumentName' => 'Instrument Name',
                ],
            ],
            4 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            6 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'User Dept Review Comments',
                ],
            ],
            8 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Review Comments',
                ],
            ],
        ],

        /* Calibration Management */
        5 => [
            23 => [
                'process_fields' => [
                    'comments' => 'Comments',
                ],
            ],
            26 => [
                'process_fields' => [
                    'implementorComments' => 'Implementor Comments',
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
            9 => [
                'grid_fields' => [
                    'equipmentInstrumentName' => 'Instrument Name',
                ],
                'week_dates_required' => true,
            ],
            12 => [
                'process_fields' => [
                    'hod_review_comments' => 'HOD / Designee Review Comments',
                ],
            ],
            14 => [
                'process_fields' => [
                    'user_dept_review_comments' => 'QA Review Comments',
                ],
            ],
            16 => [
                'process_fields' => [
                    'qa_review_comments' => 'QA Approver Comments',
                ],
            ],
        ],

        /* Preventive Maintenance */
        4 => [
            17 => [
                'process_fields' => [
                    'equipmentInstrumentName' => 'Equipment Name',
                    'equipmentInstrumentId' => 'Equipment Code',
                    'block' => 'Block',
                    'department' => 'Department',
                    'location' => 'Location',
                    'previousPreventiveDate' => 'Previous Preventive Date',
                    'remark' => 'Remark',
                ],
            ],
            20 => [
                'process_fields' => [
                    'engineer_review_comments' => 'Engineer Department Review Comments',
                ],
            ],
            22 => [
                'process_fields' => [
                    'qa_approval_comments' => 'QA Approval Comments',
                ],
            ],
        ],        
    ];

    public static function validateActivity(
        ProcessRecord $processRecord,
        int $activityId
    ): ?string {
        $processId = (int) $processRecord->process_id;

        $rules = self::RULES[$processId][$activityId] ?? null;

        if ($rules === null) {

            return null;
        }

        $processData = self::normalize($processRecord->process_data);

        foreach ($rules['process_fields'] ?? [] as $key => $label) {
            if (!self::hasProcessValue($processData, $key)) {
                \Log::warning('Stage validation failed', [
                    'process_id' => $processId,
                    'activity_id' => $activityId,
                    'missing_field' => $key,
                ]);

                return "Please enter {$label} before proceeding.";
            }
        }

        if (!empty($rules['grid_fields'])) {
            $gridRecords = GridRecord::where(
                'process_record_id',
                $processRecord->id
            )->get();

            if ($gridRecords->isEmpty()) {
                return 'Please add at least one grid record before proceeding.';
            }

            foreach ($gridRecords as $index => $gridRecord) {
                $gridData = self::normalize($gridRecord->grid_data);

                foreach ($rules['grid_fields'] as $key => $label) {
                    if (!self::hasGridValue($gridData, $key)) {
                        return "{$label} is required in grid row " . ($index + 1) . '.';
                    }
                }

                if (!empty($rules['week_dates_required'])) {
                    $weekDates = self::normalize($gridRecord->week_dates_data);

                    if (empty($weekDates)) {
                        return 'Weekly preventive dates are required in grid row ' . ($index + 1) . '.';
                    }

                    $weeklyPreventive = self::getGridFieldValue(
                        $gridData,
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
                            return "Weekly preventive dates for {$month} are missing in grid row " . ($index + 1) . '.';
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
                                return "A weekly preventive date is missing for {$month}, grid row " . ($index + 1) . '.';
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    private static function hasProcessValue(
        array $data,
        string $key
    ): bool {
        foreach ($data as $field) {
            if (
                is_array($field) &&
                ($field['key'] ?? null) === $key
            ) {
                return self::hasValue($field['value'] ?? null);
            }
        }

        return false;
    }

    private static function hasGridValue(
        array $data,
        string $key
    ): bool {
        $value = self::getGridFieldValue($data, $key);

        return self::hasValue($value);
    }

    private static function getGridFieldValue(
        array $data,
        string $key
    ) {
        foreach ($data as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (
                isset($entry[$key]) &&
                is_array($entry[$key])
            ) {
                return $entry[$key]['value'] ?? null;
            }

            if (($entry['key'] ?? null) === $key) {
                return $entry['value'] ?? null;
            }
        }

        return null;
    }

    private static function normalize($data): array
    {
        if (is_string($data)) {
            $decoded = json_decode($data, true);

            return is_array($decoded) ? $decoded : [];
        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        return is_array($data) ? $data : [];
    }

    private static function hasValue($value): bool
    {
        return $value !== null
            && !(is_string($value) && trim($value) === '')
            && !(is_array($value) && empty($value));
    }
}