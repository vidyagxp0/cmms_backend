<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\CalibrationManagementService;
use App\Services\UserReport\CalibrationReportService;
use Illuminate\Http\Request;

class CalibrationManagementController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return CalibrationManagementService::storeCalibrationManagementData($request);
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
            return CalibrationManagementService::getCalibrationManagementRecord($id);
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
            return CalibrationManagementService::updateCalibrationManagementRecord(
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
            return CalibrationManagementService::moveStage(
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
    // public function calibrationManagementSingleReport($id) 
    // {
    //     try {
    //         return CalibrationReportService::generateCalibrationManagementReport(
    //             $id
    //         );
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load report.',
    //         ], 500);
    //     }
    // }
}