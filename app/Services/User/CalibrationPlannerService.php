<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Models\ProcessRecord;
use App\Helpers\UserAuditHelper;
use App\Models\Stage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CalibrationPlannerService
{

    /* store process record */
    public static function storeCalibrationProcessData(Request $request)
    {
        DB::beginTransaction();

        try {
            /* check stage belongs to selected process */
            $stageExists = Stage::where('id', $request->stage_id)
                ->where('process_id', $request->process_id)
                ->exists();

            if (!$stageExists) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected stage does not belong to the selected process.',
                    422
                );
            }

            $processRecord = ProcessRecord::create([
                'process_id' => $request->process_id,
                'stage_id' => $request->stage_id,
                'department_id' => $request->department_id,
                'initiator_id' => $request->initiator_id,
                'short_description' => $request->short_description,
                'initiation_date' => $request->initiation_date,
                'process_data' => $request->process_data,
            ]);

            /* audit data */
            $newValue = [
                'process_id' => $processRecord->process_id,
                'stage_id' => $processRecord->stage_id,
                'department_id' => $processRecord->department_id,
                'initiator_id' => $processRecord->initiator_id,
                'short_description' => $processRecord->short_description,
                'initiation_date' => $processRecord->initiation_date,
                'process_data' => $processRecord->process_data,
            ];

            UserAuditHelper::log(
                'Process Record',
                'Created',
                'Calibration Planner record created successfully.',
                $processRecord->id,
                null,
                $newValue,
                ProcessRecord::class
            );

            DB::commit();

            $processRecord->load([
                'process',
                'stage',
                'department',
                'initiator',
            ]);

            return ResponseHelper::success(
                $processRecord,
                'Process record created successfully.',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to create process record.',
                500
            );
        }
    }


    /* get process record details */
    public static function getCalibrationPlannerRecord($id)
    {
        try {
            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            return ResponseHelper::success(
                $processRecord,
                'Process record fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Process record not found.',
                404
            );
        }
    }


    /* update process record */
    public static function updateCalibrationPlannerRecord(Request $request,$id)
    {
        DB::beginTransaction();

        try {
            $processRecord = ProcessRecord::findOrFail($id);

            $processId = $request->process_id;
            $stageId = $request->stage_id;

            /* check stage belongs to selected process */
            $stageExists = Stage::where('id', $stageId)
                ->where('process_id', $processId)
                ->exists();

            if (!$stageExists) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected stage does not belong to the selected process.',
                    422
                );
            }

            $oldValue = [];
            $newValue = [];
            $updateData = [];

            /* process */
            if ($request->has('process_id') && $request->process_id != $processRecord->process_id) {
                $oldValue['process_id'] = $processRecord->process_id;
                $newValue['process_id'] = $request->process_id;

                $updateData['process_id'] = $request->process_id;
            }

            /* stage */
            if ($request->has('stage_id') && $request->stage_id != $processRecord->stage_id) {
                $oldValue['stage_id'] = $processRecord->stage_id;
                $newValue['stage_id'] = $request->stage_id;

                $updateData['stage_id'] = $request->stage_id;
            }

            /* department */
            if ($request->has('department_id') && $request->department_id != $processRecord->department_id) {
                $oldValue['department_id'] = $processRecord->department_id;
                $newValue['department_id'] = $request->department_id;

                $updateData['department_id'] = $request->department_id;
            }

            /* initiator */
            if ($request->has('initiator_id') && $request->initiator_id != $processRecord->initiator_id) {
                $oldValue['initiator_id'] = $processRecord->initiator_id;
                $newValue['initiator_id'] = $request->initiator_id;

                $updateData['initiator_id'] = $request->initiator_id;
            }

            /* short description */
            if ($request->has('short_description') && $request->short_description !=$processRecord->short_description) {
                $oldValue['short_description'] = $processRecord->short_description;
                $newValue['short_description'] = $request->short_description;

                $updateData['short_description'] = $request->short_description;
            }

            /* initiation date */
            if ($request->has('initiation_date') && $request->initiation_date !=$processRecord->initiation_date?->format('Y-m-d')) {
                $oldValue['initiation_date'] = $processRecord->initiation_date?->format('Y-m-d');
                $newValue['initiation_date'] = $request->initiation_date;

                $updateData['initiation_date'] = $request->initiation_date;
            }

            /* process data */
            if ($request->has('process_data') && $request->process_data != $processRecord->process_data) {
                $oldValue['process_data'] = $processRecord->process_data;
                $newValue['process_data'] = $request->process_data;

                $updateData['process_data'] = $request->process_data;
            }

            /* update only changed fields */
            if (!empty($updateData)) {

                $processRecord->update($updateData);

                /* audit only changed fields */
                UserAuditHelper::log(
                    'Process Record',
                    'Updated',
                    'Process record updated successfully.',
                    $processRecord->id,
                    $oldValue,
                    $newValue,
                    ProcessRecord::class
                );
            }
            DB::commit();

            $processRecord->load([
                'process',
                'stage',
                'department',
                'initiator',
            ]);

            return ResponseHelper::success(
                $processRecord,
                'Process record updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                'Failed to update process record.',
                500
            );
        }
    }
}