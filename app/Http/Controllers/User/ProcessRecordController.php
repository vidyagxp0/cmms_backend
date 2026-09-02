<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Services\User\ProcessRecordService;
use Illuminate\Http\Request;

class ProcessRecordController extends Controller
{
    /* all process records */
    public function getEngineeringRecord(Request $request)
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
    
        /* generate record number for a process */
    public function generateRecordNumber($processId)
    {
        try {
            return ProcessRecordService::generateRecordNumber($processId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate record number.',
            ], 500);
        }
    }

    /* user permission */
    public function checkRecordPermission($id) 
    {
        try {
            return ProcessRecordService::checkRecordPermission(
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