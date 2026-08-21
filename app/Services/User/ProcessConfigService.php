<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Models\Activity;
use App\Models\Process;

class ProcessConfigService
{
    /* get all processes */
    public static function getProcesses()
    {
        try {
            $processes = Process::where('is_active', true)
                ->orderBy('id')
                ->get();

            return ResponseHelper::success(
                $processes,
                'Processes fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve processes.',
                500
            );
        }
    }

    /* get stages of process */
    public static function getProcessStages($processId)
    {
        try {
            $process = Process::findOrFail($processId);
            $stages = $process->stages()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            return ResponseHelper::success(
                $stages,
                'Stages fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve stages.',
                500
            );
        }
    }

    /* get activities of current stage */
    public static function getStageActivities($stageId)
    {
        try {
            $activities = Activity::where('from_stage', $stageId)
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            return ResponseHelper::success(
                $activities,
                'Activities fetched successfully.'
            );
        } catch (\Exception $e) {
            /* 
                info('Error in ProcessService@getStageActivities', [
                    'error' => $e
                ]);
            */           

            return ResponseHelper::error(
                'Failed to retrieve activities.',
                500
            );
        }
    }
}