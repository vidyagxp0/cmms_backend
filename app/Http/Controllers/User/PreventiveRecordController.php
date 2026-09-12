<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\PreventivePlanner\PreventiveRecordService;
use App\Services\UserReport\PreventiveMaintenancePlannerReportService;
use Illuminate\Http\Request;
use App\Http\Requests\User\RecordActivityRequest;

class PreventiveRecordController extends Controller
{
     /* store process record */
    public function store(Request $request)
    {
        try {
            return PreventiveRecordService::storePreventivePlannerRecord($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create process record.',
            ], 500);
        }
    }

    /* process record details */
    public function show($id)
    {
        try {
            return PreventiveRecordService::getPreventivePlannerRecord($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process record.',
            ], 500);
        }
    }

    /* update process record */
    public function update(Request $request,$id) 
    {
        try {
            return PreventiveRecordService::updatePreventivePlannerRecord(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update process record.',
            ], 500);
        }
    }

    /* move process record stage */
    public function moveStage(RecordActivityRequest $request, $id) 
    {
        try {
            return PreventiveRecordService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update process record stage.',
            ], 500);
        }
    }

    /* preventive planner single report */
    public function preventivePlannerSingleReport($id) 
    {
        try {
            return PreventiveMaintenancePlannerReportService::generateReport(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report.',
            ], 500);
        }
    }

}
