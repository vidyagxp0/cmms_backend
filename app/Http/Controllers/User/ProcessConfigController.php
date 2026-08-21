<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\ProcessConfigService;
use Illuminate\Http\Request;

class ProcessConfigController extends Controller
{
    /* process listing function */
    public function getProcesses()
    {
        try {
            return ProcessConfigService::getProcesses();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process.',
            ], 500);
        }
    }

    /* get process stages */
    public function getProcessStages($processId)
    {
        try {
            return ProcessConfigService::getProcessStages($processId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stages.',
            ], 500);
        }
    }

    /* get activities based on stages */
    public function getStageActivities($stageId)
    {
        try {
            return ProcessConfigService::getStageActivities($stageId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activties.',
            ], 500);
        }
    }
}
