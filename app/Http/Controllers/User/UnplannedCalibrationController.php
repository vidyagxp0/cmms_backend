<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\CalibrationPlanner\CalibrationPlannerDetailService;
use App\Services\User\UnplannedCalibrationPlanner\UnplannedCalibrationService;
use App\Services\User\UserAuditService;
use App\Services\UserReport\CalibrationReportService;
use App\Services\UserReport\UnplannedCalibrationReportService;
use Illuminate\Http\Request;

class UnplannedCalibrationController extends Controller
{
    /* store process record */
    public function store(Request $request)
    {
        try {
            return UnplannedCalibrationService::storeCalibrationProcessData($request);
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
            return CalibrationPlannerDetailService::getCalibrationPlannerRecord($id);
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
            return UnplannedCalibrationService::updateCalibrationPlannerRecord(
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
            return UnplannedCalibrationService::moveStage(
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
    public function unplannedCalibrationSingleReport($id) 
    {
        try {
            return UnplannedCalibrationReportService::generateReport(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load report.',
            ], 500);
        }
    }

    public function unplannedCalibrationAuditListing(Request $request, $recordId)
    {
        try {
            return UserAuditService::getProcessRecordAudits(
                $recordId,
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