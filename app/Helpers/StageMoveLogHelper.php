<?php

namespace App\Helpers;

use App\Models\Activity;
use App\Models\RecordNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class StageMoveLogHelper
{
    /* logs why an activity cannot be performed on the current stage of a record */
    public static function activityUnavailable(Model $record, $activityId): void
    {
        $activity = Activity::find($activityId);
        $stageId = $record->stage_id;
        $reason = match (true) {
            !$activity => 'Activity id does not exist.',
            !$activity->is_active => 'Activity is inactive.',
            (int) $activity->to_stage === (int) $stageId => 'Record is already at the target stage of this activity (already performed or submitted twice).',
            (int) $activity->from_stage !== (int) $stageId => 'Activity belongs to another stage than the current stage of the record.',
            default => 'Activity matches the stage but was not returned by the query.',
        };
    }


    private const STAGE_TRANSITIONS = [

        // 1 - Calibration Planner
        1 => [
            [2, 3],
            [3, 4],
            [4, 5],
        ],

        // 2 - Preventive Maintenance Planner
        2 => [
            [8, 9],
            [9, 10],
            [10, 11],
        ],

        // 4 - Preventive Maintenance
        4 => [
            [14, 15],
            [15, 16],
        ],

        // 5 - Calibration Management
        5 => [
            [19, 20],
            [20, 21],
        ],

        // 8 - Field Instrument Calibration Addendum
        8 => [
            [40, 41],
            [41, 42],
            [42, 43],
        ],

        // 11 - Preventive Addendum
        11 => [
            [56, 57],
            [57, 58],
        ],

        // 12 - Addendum Calibration Management
        12 => [
            [61, 62],
            [62, 63],
        ],

        // 13 - Addendum Preventive Management
        13 => [
            [66, 67],
            [67, 68],
        ],

        // 14 - Unplanned Calibration Planner
        14 => [
            [71, 72],
            [72, 73],
            [73, 74],
        ],

        // 15 - Unplanned Calibration Management
        15 => [
            [77, 78],
            [78, 79],
        ],
    ];

    /* complete pending notifications for an allowed stage transition */
    public static function completeNotifications(
        Model $record,
        int $fromStageId,
        int $toStageId,
        ?int $performedBy = null
    ): void {
        try {
            $processId = (int) ($record->getAttributes()['process_id'] ?? 0);

            $allowedTransitions = self::STAGE_TRANSITIONS[$processId] ?? [];

            $isAllowed = in_array(
                [$fromStageId, $toStageId],
                $allowedTransitions,
                true
            );

            if (!$isAllowed) {
                return;
            }

            RecordNotification::pending()
                ->where('record_type', $record->getMorphClass())
                ->where('record_id', $record->getKey())
                ->where('process_id', $processId)
                ->where('stage_id', $fromStageId)
                ->update([
                    'status' => RecordNotification::STATUS_COMPLETED,
                    'completed_by' => $performedBy,
                    'completed_at' => now(),
                ]);

        } catch (\Throwable $e) {
            \Log::error('STAGE NOTIFICATION COMPLETION ERROR', [
                'record_id' => $record->getKey(),
                'process_id' => $record->getAttributes()['process_id'] ?? null,
                'from_stage' => $fromStageId,
                'to_stage' => $toStageId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }
}