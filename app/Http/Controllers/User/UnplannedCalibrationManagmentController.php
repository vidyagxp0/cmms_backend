<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\CalibrationPlanner\CalibrationManagementAuditService;
use App\Services\User\UnplannedCalibrationManagement\UnplannedCalibrationManagementAudit;
use App\Services\User\UnplannedCalibrationManagement\UnplannedCalibrationManagementService;
use App\Services\UserReport\CalibrationManagementReportService;
use App\Services\UserReport\UnplannedCalibrationManagementReport;
use Illuminate\Http\Request;

class UnplannedCalibrationManagmentController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return UnplannedCalibrationManagementService::storeCalibrationManagementData($request);
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
            return UnplannedCalibrationManagementService::getCalibrationManagementRecord($id);
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
            return UnplannedCalibrationManagementService::updateCalibrationManagementRecord(
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
            return UnplannedCalibrationManagementService::moveStage(
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
    public function unplannedCalibrationManagementSingleReport($id) 
    {
        try {
            return UnplannedCalibrationManagementReport::generateReport(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report.',
            ], 500);
        }
    }

    /* get calibration management audit data */
    public function unplannedCalibrationManagementAudit(Request $request, $id)
    {
        try {
            return UnplannedCalibrationManagementAudit::getProcessRecordAudits(
                $id,
                $request
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve process record audits.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}