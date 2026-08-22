<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\EquipmentMasterRequest;
use App\Models\EquipmentMaster;
use Illuminate\Support\Facades\DB;

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

            $updateData = [];

            if ($request->has('name')) {
                $updateData['name'] = $request->name;
            }

            if ($request->has('equipment_id')) {
                $updateData['equipment_id'] = $request->equipment_id;
            }

            if ($request->has('make')) {
                $updateData['make'] = $request->make;
            }

            if ($request->has('model')) {
                $updateData['model'] = $request->model;
            }

            if ($request->has('equipment_type')) {
                $updateData['equipment_type'] = $request->equipment_type;
            }

            if (!empty($updateData)) {
                $equipment->update($updateData);
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
}