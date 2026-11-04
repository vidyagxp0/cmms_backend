<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\AddendumCalibrationManagementService\AddendumCalibrationManagementRecord;
use App\Services\User\CalibrationPlanner\CalibrationManagementAuditService;
use App\Services\UserReport\AddendumCalibrationManagementReport;
use App\Services\UserReport\CalibrationManagementReportService;
use Illuminate\Http\Request;

class AddendumCalibrationManagementController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return AddendumCalibrationManagementRecord::storeAddendumCalibrationManagementData($request);
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
            return AddendumCalibrationManagementRecord::getAddendumCalibrationManagementRecord($id);
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
            return AddendumCalibrationManagementRecord::updateAddendumCalibrationManagementRecord(
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
            return AddendumCalibrationManagementRecord::moveStage(
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
    public function calibrationManagementSingleReport($id) 
    {
        try {
            return AddendumCalibrationManagementReport::generateReport(
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
    public function calibrationManagementAudit(Request $request, $id)
    {
        try {
            return CalibrationManagementAuditService::getProcessRecordAudits(
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