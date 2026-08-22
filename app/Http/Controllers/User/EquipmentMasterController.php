<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Services\User\EquipmentMasterService;

class EquipmentMasterController extends Controller
{
    /* equipment listing function */
    public function index(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::getEquipments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve equipment.',
            ], 500);
        }
    }

    /* equipment details function */
    public function show($id)
    {
        try {
            return EquipmentMasterService::getEquipmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve equipment.',
            ], 500);
        }
    }

    /* store equipment function */
    public function store(EquipmentMasterRequest $request)
    {
        try {
            return EquipmentMasterService::storeEquipment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create equipment.',
            ], 500);
        }
    }

    /* update equipment function */
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
                'message' => 'Failed to update equipment.',
            ], 500);
        }
    }
}