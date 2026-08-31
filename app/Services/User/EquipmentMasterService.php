<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Models\EquipmentMaster;
use Illuminate\Support\Facades\DB;
use App\Helpers\UserAuditHelper;

class EquipmentMasterService
{
    /* equipment listing */
    public static function getEquipments(EquipmentMasterRequest $request)
    {
        try {
            $equipments = EquipmentMaster::query()

                ->when($request->search, function ($query) use ($request) {
                    $search = $request->search;

                    $query->where(function ($query) use ($search) {
                        $query->where('name', 'like', "%{$search}%")
                            ->orWhere('equipment_id', 'like', "%{$search}%")
                            ->orWhere('make', 'like', "%{$search}%")
                            ->orWhere('model', 'like', "%{$search}%")
                            ->orWhere('equipment_type', 'like', "%{$search}%");
                    });
                })

                ->when($request->equipment_type, function ($query) use ($request) {
                    $query->where(
                        'equipment_type',
                        $request->equipment_type
                    );
                })

                ->orderBy('id', 'desc')
                ->paginate($request->per_page ?? 10);

            return ResponseHelper::success(
                $equipments,
                'Equipment fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve equipment.',
                500
            );
        }
    }

    /* get equipment details */
    public static function getEquipmentById($id)
    {
        try {
            $equipment = EquipmentMaster::findOrFail($id);

            return ResponseHelper::success(
                $equipment,
                'Equipment fetched successfully.'
            );

        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Equipment not found.',
                404
            );
        }
    }

    /* store equipment */
    public static function storeEquipment(EquipmentMasterRequest $request)
    {
        DB::beginTransaction();

        try {
            $equipment = EquipmentMaster::create([
                'name' => $request->name,
                'equipment_id' => $request->equipment_id,
                'make' => $request->make,
                'model' => $request->model,
                'equipment_type' => $request->equipment_type,
            ]);

            /* audit data */
            $newValue = [
                'name' => $equipment->name,
                'equipment_id' => $equipment->equipment_id,
                'make' => $equipment->make,
                'model' => $equipment->model,
                'equipment_type' => $equipment->equipment_type,
            ];

            UserAuditHelper::log(
                'Equipment Master',
                'Created',
                'Equipment created successfully.',
                $equipment->id,
                null,
                $newValue,
                EquipmentMaster::class
            );
            DB::commit();

            return ResponseHelper::success(
                $equipment,
                'Equipment created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to create equipment.',
                500
            );
        }
    }

/* update equipment */
    public static function updateEquipment(EquipmentMasterRequest $request,$id)
    {
        DB::beginTransaction();

        try {
            $equipment = EquipmentMaster::findOrFail($id);

            $oldValue = [];
            $newValue = [];
            $updateData = [];

            /* name */
            if ($request->has('name') &&$request->name != $equipment->name)
            {
                $oldValue['name'] = $equipment->name;
                $newValue['name'] = $request->name;

                $updateData['name'] = $request->name;
            }

            /* equipment id */
            if ($request->has('equipment_id') &&$request->equipment_id != $equipment->equipment_id)
            {
                $oldValue['equipment_id'] = $equipment->equipment_id;
                $newValue['equipment_id'] = $request->equipment_id;

                $updateData['equipment_id'] = $request->equipment_id;
            }

            /* make */
            if ($request->has('make') &&$request->make != $equipment->make)
            {
                $oldValue['make'] = $equipment->make;
                $newValue['make'] = $request->make;

                $updateData['make'] = $request->make;
            }

            /* model */
            if ($request->has('model') &&$request->model != $equipment->model)
            {
                $oldValue['model'] = $equipment->model;
                $newValue['model'] = $request->model;

                $updateData['model'] = $request->model;
            }

            /* equipment type */
            if ($request->has('equipment_type') &&$request->equipment_type != $equipment->equipment_type)
            {
                $oldValue['equipment_type'] = $equipment->equipment_type;
                $newValue['equipment_type'] = $request->equipment_type;

                $updateData['equipment_type'] = $request->equipment_type;
            }

            /* update only changed fields */
            if (!empty($updateData)) {

                $equipment->update($updateData);

                /* audit only changed fields */
                UserAuditHelper::log(
                    'Equipment Master',
                    'Updated',
                    'Equipment updated successfully.',
                    $equipment->id,
                    $oldValue,
                    $newValue,
                    EquipmentMaster::class
                );
            }
            DB::commit();

            return ResponseHelper::success(
                $equipment,
                'Equipment updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to update equipment.',
                500
            );
        }
    }

    public static function getAllEquipments()
    {
        try {
            $equipments = EquipmentMaster::orderBy('id', 'asc')->get();

            return ResponseHelper::success(
                $equipments,
                'Equipment fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve equipment.',
                500
            );
        }
    }
}