<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\EquipmentMasterService;
use Illuminate\Http\Request;

class EquipmentMasterController extends Controller
{
    /* Instrument listing function */
    public function index(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::getEquipments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* Instrument details function */
    public function show($id)
    {
        try {
            return EquipmentMasterService::getEquipmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* store Instrument function */
    public function store(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::storeEquipment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Instrument.',
            ], 500);
        }
    }

    /* update Instrument function */
    public function update(EquipmentMasterRequest $request,$id)
    {
        try {
            return EquipmentMasterService::updateEquipment(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Instrument.',
            ], 500);
        }
    }


    public function getAllEquipments()
    {
        try {
            return EquipmentMasterService::getAllEquipments();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* Instrument checklist function */
    public function getChecklistByFrequency($equipmentId, $frequency)
    {
        try {
            return EquipmentMasterService::getChecklistByFrequency($equipmentId, $frequency);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument checklist.',
            ], 500);
        }
    }

    /* Instrument audit trail function */
    public function getInstrumentAuditTrail(Request $request, $id)
    {
        try {
            return EquipmentMasterService::getInstrumentAuditTrail($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Instrument.',
            ], 500);
        }
    }

    /* move Instrument Master process record stage */
    public function moveStage(RecordActivityRequest $request, $id)
    {
        try {
            return EquipmentMasterService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Instrument Master stage.',
            ], 500);
        }
    }

    /* user permission */
    public function checkInstrumentRecordPermission($id) 
    {
        try {
            return EquipmentMasterService::checkInstrumentRecordPermission(
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load data.',
            ], 500);
        }
    }
}