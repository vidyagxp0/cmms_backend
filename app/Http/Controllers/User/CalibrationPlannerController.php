<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\CalibrationPlannerService;
use App\Services\UserReport\CalibrationReportService;
use Illuminate\Http\Request;

class CalibrationPlannerController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return CalibrationPlannerService::storeCalibrationProcessData($request);
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
            return CalibrationPlannerService::getCalibrationPlannerRecord($id);
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
            return CalibrationPlannerService::updateCalibrationPlannerRecord(
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
            return CalibrationPlannerService::moveStage(
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

    /* calibration single report */
    public function calibrationSingleReport($id) 
    {
        try {
            return CalibrationReportService::generateReport(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report.',
            ], 500);
        }
    }

    /* user permission */
    public function checkRecordPermission($id) 
    {
        try {
            return CalibrationPlannerService::checkRecordPermission(
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