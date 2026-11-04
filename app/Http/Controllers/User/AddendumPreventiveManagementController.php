<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\AddendumPreventiveManagementService\AddendumPreventiveManagementRecord;
use App\Services\UserReport\AddendumPreventiveManagementReport;
use App\Services\UserReport\PreventiveMaintenanceReportService;
use Illuminate\Http\Request;
use App\Http\Requests\User\RecordActivityRequest;

class AddendumPreventiveManagementController extends Controller
{
     /* store process record */
    public function store(Request $request)
    {
        try {
            return AddendumPreventiveManagementRecord::storeAddendumPreventiveMaintenanceRecord($request);
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
            return AddendumPreventiveManagementRecord::getAddendumPreventiveMaintenanceRecord($id);
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
            return AddendumPreventiveManagementRecord::updateAddendumPreventiveMaintenanceRecord(
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
            return AddendumPreventiveManagementRecord::moveStage(
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
    public function preventiveMaintenanceReport($id) 
    {
        try {
            return AddendumPreventiveManagementReport::generateReport(
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
