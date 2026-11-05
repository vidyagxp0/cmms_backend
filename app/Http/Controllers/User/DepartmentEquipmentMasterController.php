<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\RecordActivityRequest;
use App\Services\User\DepartmentEquipment\DepartmentEquipmentMasterService;
use Illuminate\Http\Request;

class DepartmentEquipmentMasterController extends Controller
{
    /* Equipmentn listing function */
    public function index(Request $request)
    {
        try {
            return DepartmentEquipmentMasterService::getEquipments($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipmentn.',
            ], 500);
        }
    }

    /* Equipmentn details function */
    public function show($id)
    {
        try {
            return DepartmentEquipmentMasterService::getEquipmentById($id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipmentn.',
            ], 500);
        }
    }

    /* store Equipmentn function */
    public function store(Request $request)
    {
        try {
            return DepartmentEquipmentMasterService::storeEquipment($request);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create Equipmentn.',
            ], 500);
        }
    }

    /* update Equipmentn function */
    public function update(Request $request,$id)
    {
        try {
            return DepartmentEquipmentMasterService::updateEquipment(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Equipmentn.',
            ], 500);
        }
    }


    public function getAllEquipments()
    {
        try {
            return DepartmentEquipmentMasterService::getAllEquipments();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipmentn.',
            ], 500);
        }
    }

    /* get all equipment with no imported filter */
    public function getAllEquipmentData()
    {
        try {
            return DepartmentEquipmentMasterService::getAllEquipmentsData();
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipmentn.',
            ], 500);
        }
    }

    /* Equipmentn audit trail function */
    public function getInstrumentAuditTrail(Request $request, $id)
    {
        try {
            return DepartmentEquipmentMasterService::getInstrumentAuditTrail($request, $id);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve Equipmentn.',
            ], 500);
        }
    }

    /* move Equipmentn Master process record stage */
    public function moveStage(RecordActivityRequest $request, $id)
    {
        try {
            return DepartmentEquipmentMasterService::moveStage(
                $request,
                $id
            );
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update Equipmentn Master stage.',
            ], 500);
        }
    }

    /* user permission */
    public function checkEquipmentMasterRecordPermission($id) 
    {
        try {
            return DepartmentEquipmentMasterService::checkEquipmentMasterRecordPermission(
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