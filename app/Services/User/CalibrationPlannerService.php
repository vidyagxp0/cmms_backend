<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Http\Requests\User\ProcessRecordRequest;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\ProcessRecord;
use App\Helpers\UserAuditHelper;
use App\Models\Stage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Requests\User\MoveProcessRecordRequest;
use App\Models\Activity;
use App\Models\RecordActivityHistory;
use Illuminate\Support\Facades\Auth;

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

    /* move process record stage */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* get activity details */
            $activity = Activity::with([
                'fromStage',
                'toStage',
            ])
            ->where('id', $request->activity_id)
            ->where('is_active', true)
            ->where('from_stage', $processRecord->stage_id)
            ->first();

            if (!$activity) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected activity is not available for the current stage.',
                    422
                );
            }

            /* make sure activity belongs to current process */
            if (!$activity->fromStage || $activity->fromStage->process_id != $processRecord->process_id)
            {
                DB::rollBack();
                return ResponseHelper::error(
                    'Selected activity does not belong to this process.',
                    422
                );
            }

            /* make sure stage belongs to current process */
            if (!$activity->toStage || $activity->toStage->process_id != $processRecord->process_id)
            {
                DB::rollBack();
                return ResponseHelper::error(
                    'Invalid target stage for this process.',
                    422
                );
            }

            $currentStage = $processRecord->stage;
            $targetStage = $activity->toStage;

            /* record activity code */
            RecordActivityHistory::create([
                'process_record_id' => $processRecord->id,
                'activity_id' => $activity->id,
                'performed_by' => Auth::id(),
                'stage_id' => $currentStage->id,
                'target_stage' => $targetStage->id,
                'comment' => $request->comment,
                'performed_at' => now(),
            ]);

            /* update current stage */
            $processRecord->stage_id = $targetStage->id;
            $processRecord->save();

            /* audit code */
            $oldValue = [
                'process' => $processRecord->process?->name,
                'stage' => $currentStage?->name,
            ];

            $newValue = [
                'process' => $processRecord->process?->name,
                'stage' => $targetStage?->name,
                'activity' => $activity->name,
            ];

            if ($request->filled('comment')) {
                $newValue['comment'] = $request->comment;
            }

            UserAuditHelper::log(
                'Process Record',
                'Activity Performed',
                'Process record stage updated successfully.',
                $processRecord->id,
                $oldValue,
                $newValue,
                ProcessRecord::class
            );

            DB::commit();

            /* load latest data */
            $processRecord->load([
                'process',
                'stage',
                'department',
                'initiator',
            ]);

            return ResponseHelper::success(
                [
                    'record' => $processRecord,
                    'activity' => [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'from_stage' => $currentStage->name,
                        'to_stage' => $targetStage->name,
                    ],
                ],
                'Process record stage updated successfully.'
            );
        } catch (\Exception $e) {
            DB::rollBack();

            return ResponseHelper::error(
                $e->getMessage(),
                500
            );
        }
    }
}