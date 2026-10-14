<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Services\User\EquipmentMasterService;

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
}