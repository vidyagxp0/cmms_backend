<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Services\User\ProcessRecordService;

class ProcessRecordController extends Controller
{
    /* Equipment Master records */
    public function equipmentMaster(ProcessRecordRequest $request)
    {
        try {
            return ProcessRecordService::getEquipmentMasterRecords($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipment Master Records.',
            ], 500);
        }
    }

    /* all process records */
    public function getEngineeringRecord(ProcessRecordRequest $request)
    {
        try {
            return ProcessRecordService::getEngineeringRecords($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Records.',
            ], 500);
        }
    }
}