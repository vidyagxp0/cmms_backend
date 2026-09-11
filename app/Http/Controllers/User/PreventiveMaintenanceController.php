<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Services\User\PreventivePlanner\PreventiveMaintenanceService;
use Illuminate\Http\Request;
use App\Http\Requests\User\RecordActivityRequest;

class PreventiveMaintenanceController extends Controller
{
     /* store process record */
    public function store(Request $request)
    {
        try {
            return PreventiveMaintenanceService::storePreventiveMaintenanceRecord($request);
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
            return PreventiveMaintenanceService::getPreventiveMaintenanceRecord($id);
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
            return PreventiveMaintenanceService::updatePreventiveMaintenanceRecord(
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
            return PreventiveMaintenanceService::moveStage(
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

}
