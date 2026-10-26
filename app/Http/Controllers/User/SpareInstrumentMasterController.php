<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\SpareInstrumentMaster\SpareInstrumentMasterService;
use Illuminate\Http\Request;

class SpareInstrumentMasterController extends Controller
{
    /* field instrument listing */
    public function index(Request $request)
    {
        try {
            return SpareInstrumentMasterService::getSpareInstruments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Spare instruments.',
            ], 500);
        }
    }

    /* Spare instrument details */
    public function show($id)
    {
        try {
            return SpareInstrumentMasterService::getSpareInstrumentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Spare instrument.',
            ], 500);
        }
    }

    /* create Spare instrument */
    public function store(Request $request)
    {
        try {
            return SpareInstrumentMasterService::storeSpareInstrumentMaster($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Spare instrument.',
            ], 500);
        }
    }

    /* update Spare instrument */
    public function update(Request $request, $id)
    {
        try {
            return SpareInstrumentMasterService::updateSpareInstrumentMaster(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Spare instrument.',
            ], 500);
        }
    }

    /* get all Spare instruments */
    public function getAllSpareInstrument()
    {
        try {
            return SpareInstrumentMasterService::getAllSpareInstrument();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Spare instruments.',
            ], 500);
        }
    }

    /* Spare instrument audit trail */
    public function getSpareInstrumentAuditTrail(Request $request, $id)
    {
        try {
            return SpareInstrumentMasterService::getSpareInstrumentAuditTrail($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Spare instrument audit trail.',
            ], 500);
        }
    }

    /* move Spare Instrument Master process record stage */
    public function moveStage(RecordActivityRequest $request, $id)
    {
        try {
            return SpareInstrumentMasterService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Spare Instrument Master stage.',
            ], 500);
        }
    }

    /* user permission */
    public function checkSpareInstrumentRecordPermission($id) 
    {
        try {
            return SpareInstrumentMasterService::checkSpareInstrumentRecordPermission(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data.',
            ], 500);
        }
    }

    /* instrument single report */
    // public function SpareInstrumentSingleReport($id) 
    // {
    //     try {
    //         return SpareInstrumentReportService::generateSpareInstrumentReport(
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
