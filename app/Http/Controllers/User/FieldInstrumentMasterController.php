<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Services\User\FieldInstrumentMaster\FieldInstrumentMasterService;

class FieldInstrumentMasterController extends Controller
{
    /* field instrument listing */
    public function index(EquipmentMasterRequest $request)
    {
        try {
            return FieldInstrumentMasterService::getEquipments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve field instruments.',
            ], 500);
        }
    }

    /* field instrument details */
    public function show($id)
    {
        try {
            return FieldInstrumentMasterService::getEquipmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve field instrument.',
            ], 500);
        }
    }

    /* create field instrument */
    public function store(EquipmentMasterRequest $request)
    {
        try {
            return FieldInstrumentMasterService::storeEquipment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create field instrument.',
            ], 500);
        }
    }

    /* update field instrument */
    public function update(EquipmentMasterRequest $request, $id)
    {
        try {
            return FieldInstrumentMasterService::updateEquipment(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update field instrument.',
            ], 500);
        }
    }

    /* get all field instruments */
    public function getAllFieldIntrument()
    {
        try {
            return FieldInstrumentMasterService::getAllEquipments();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve field instruments.',
            ], 500);
        }
    }

    /* field instrument checklist */
    public function getChecklistByFrequency($equipmentId, $frequency)
    {
        try {
            return FieldInstrumentMasterService::getChecklistByFrequency(
                $equipmentId,
                $frequency
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve field instrument checklist.',
            ], 500);
        }
    }

    /* field instrument audit trail */
    public function getFieldInstrumentAuditTrail($id)
    {
        try {
            return FieldInstrumentMasterService::getInstrumentAuditTrail($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve field instrument audit trail.',
            ], 500);
        }
    }
}
