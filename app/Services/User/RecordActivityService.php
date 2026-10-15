<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Models\RecordActivityHistory;

class RecordActivityService
{
    /* get process record activity history */
    public static function getActivityHistory($recordId, $processId = null)
    {
        try {
            $processRecordProcesses = [
                '17',
                '18',
                '20',
                '21',
            ];

            if ($processId === '19') {
                $column = 'instrument_id';
            } elseif ($processId === '22') {
                $column = 'field_instrument_id';
            } elseif (in_array($processId, $processRecordProcesses, true)) {
                $column = 'process_record_id';
            } else {
                $column = 'process_record_id';
            }

            $histories = RecordActivityHistory::with([
                    'activity',
                    'performedBy',
                    'stage',
                    'targetStage',
                ])
                ->where($column, $recordId)
                ->orderBy('performed_at', 'asc')
                ->get();

            $histories->transform(function ($history) {
                return [
                    'id' => $history->id,
                    'activity_name' => $history->activity?->name,
                    'performed_by' => $history->performedBy?->name,
                    'comment' => $history->comment,
                    'performed_at' => $history->performed_at ? $history->performed_at->format('d-m-Y H:i:s') : null,
                ];
            });

            return ResponseHelper::success(
                $histories,
                'Activity history fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve activity history.',
                500
            );
        }
    }
}