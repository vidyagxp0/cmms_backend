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
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\GridRecord;

class CalibrationPlannerService
{
    /* store process record */
    public static function storeCalibrationProcessData(Request $request)
    {
        DB::beginTransaction();

        try {
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

            /* Store grid data */
            if ($request->has('gridData') && is_array($request->gridData) && !empty($request->gridData)) {
                $gridRecord = GridRecord::create([
                    'process_record_id' => $processRecord->id,
                    'grid_data' => $request->gridData,
                ]);

                /* Grid audit */
                UserAuditHelper::log(
                    'Grid Record',
                    'Created',
                    'Grid data created successfully.',
                    $gridRecord->id,
                    null,
                    [
                        'process_record_id' => $gridRecord->process_record_id,
                        'grid_data' => $gridRecord->grid_data,
                    ],
                    GridRecord::class
                );
            }

            /* Process record audit */
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
                'Process record created successfully.',
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
                'gridRecords',
                'checklistRecords'
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
    public static function updateCalibrationPlannerRecord(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $processRecord = ProcessRecord::findOrFail($id);

            $processId = $request->has('process_id') ? $request->process_id : $processRecord->process_id;
            $stageId = $request->has('stage_id') ? $request->stage_id : $processRecord->stage_id;

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

            /* short description */
            if (
                $request->has('short_description') &&
                $request->short_description != $processRecord->short_description
            ) {
                $oldValue['short_description'] = $processRecord->short_description;
                $newValue['short_description'] = $request->short_description;
                $updateData['short_description'] = $request->short_description;
            }

            /* process data */
            if ($request->has('process_data')) {
                $oldProcessData = is_array($processRecord->process_data)
                    ? $processRecord->process_data
                    : [];
                $newProcessData = is_array($request->process_data)
                    ? $request->process_data
                    : [];

                [$processOldChanges, $processNewChanges] = self::getProcessDataChanges(
                    $oldProcessData,
                    $newProcessData
                );

                if (!empty($processNewChanges)) {
                    $oldValue['process_data'] = $processOldChanges;
                    $newValue['process_data'] = $processNewChanges;
                }

                if ($oldProcessData != $newProcessData) {
                    $updateData['process_data'] = $newProcessData;
                }
            }

            /* update process record */
            if (!empty($updateData)) {
                $processRecord->update($updateData);
            }

            /* process record audit */
            if (!empty($newValue)) {
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

            /* grid data */
            if ($request->has('gridData')) {
                $gridData = is_array($request->gridData)
                    ? $request->gridData
                    : [];
                $gridRecord = GridRecord::where(
                    'process_record_id',
                    $processRecord->id
                )->first();

                /* existing grid */
                if ($gridRecord) {
                    $oldGridData = is_array($gridRecord->grid_data)
                        ? $gridRecord->grid_data
                        : [];

                    [$gridOldChanges, $gridNewChanges] = self::getGridDataChanges(
                        $oldGridData,
                        $gridData
                    );

                    if (!empty($gridNewChanges)) {
                        UserAuditHelper::log(
                            'Grid Record',
                            'Updated',
                            'Grid data updated successfully.',
                            $gridRecord->id,
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridOldChanges,
                            ],
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridNewChanges,
                            ],
                            GridRecord::class
                        );
                    }

                    if ($oldGridData != $gridData) {
                        $gridRecord->update([
                            'grid_data' => $gridData,
                        ]);
                    }
                } elseif (!empty($gridData)) {
                    /* create grid if it does not exist */
                    $gridRecord = GridRecord::create([
                        'process_record_id' => $processRecord->id,
                        'grid_data' => $gridData,
                    ]);

                    UserAuditHelper::log(
                        'Grid Record',
                        'Created',
                        'Grid data created successfully.',
                        $gridRecord->id,
                        null,
                        [
                            'process_record_id' => $processRecord->id,
                            'grid_data' => $gridData,
                        ],
                        GridRecord::class
                    );
                }
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

            info('Error in CalibrationPlannerService@updateCalibrationPlannerRecord', [
                'error' => $e->getMessage(),
            ]);

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
            if (!$activity->fromStage || $activity->fromStage->process_id != $processRecord->process_id) {
                DB::rollBack();
                return ResponseHelper::error(
                    'Selected activity does not belong to this process.',
                    422
                );
            }

            /* make sure stage belongs to current process */
            if (!$activity->toStage || $activity->toStage->process_id != $processRecord->process_id) {
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
                'stage' => $currentStage?->name,
            ];

            $newValue = [
                'stage' => $targetStage?->name,
            ];

            $description = $request->filled('comment')
                ? $request->comment
                : 'Process record stage updated successfully.';

            UserAuditHelper::log(
                'Process Record',
                'Activity Performed',
                $description,
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

    private static function getProcessDataChanges(array $oldData, array $newData): array
    {
        $oldMap = [];
        $newMap = [];

        foreach ($oldData as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? $item['label'] ?? null;
            if ($key !== null) {
                $oldMap[$key] = $item;
            }
        }

        foreach ($newData as $item) {
            if (!is_array($item)) {
                continue;
            }
            $key = $item['key'] ?? $item['label'] ?? null;
            if ($key !== null) {
                $newMap[$key] = $item;
            }
        }

        $oldChanges = [];
        $newChanges = [];

        $allKeys = array_unique(
            array_merge(
                array_keys($oldMap),
                array_keys($newMap)
            )
        );

        foreach ($allKeys as $key) {
            $oldItem = $oldMap[$key] ?? null;
            $newItem = $newMap[$key] ?? null;

            $oldValue = $oldItem['value'] ?? null;
            $newValue = $newItem['value'] ?? null;

            if ($oldValue == $newValue) {
                continue;
            }

            $label = $newItem['label']
                ?? $oldItem['label']
                ?? $key;

            $oldChanges[] = [
                'key' => $key,
                'label' => $label,
                'value' => $oldValue,
            ];

            $newChanges[] = [
                'key' => $key,
                'label' => $label,
                'value' => $newValue,
            ];
        }

        return [$oldChanges, $newChanges];
    }

    private static function getGridDataChanges(array $oldData, array $newData): array
    {
        $oldRows = [];
        $newRows = [];

        foreach ($oldData as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowId = $row['row_id'] ?? null;
            if ($rowId !== null) {
                $oldRows[$rowId] = $row;
            }
        }

        foreach ($newData as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowId = $row['row_id'] ?? null;
            if ($rowId !== null) {
                $newRows[$rowId] = $row;
            }
        }

        $oldChanges = [];
        $newChanges = [];

        $allRows = array_unique(
            array_merge(
                array_keys($oldRows),
                array_keys($newRows)
            )
        );

        foreach ($allRows as $rowId) {
            $oldRow = $oldRows[$rowId] ?? null;
            $newRow = $newRows[$rowId] ?? null;

            /* newly added row */
            if ($oldRow === null && $newRow !== null) {
                $cleanNewRow = [];
                foreach ($newRow as $column => $value) {
                    if ($column === 'row_id') {
                        continue;
                    }
                    $cleanNewRow[$column] = $value;
                }

                if (!empty($cleanNewRow)) {
                    $cleanNewRow['row_id'] = $rowId;
                    $newChanges[] = $cleanNewRow;
                }
                continue;
            }

            /* deleted row */
            if ($oldRow !== null && $newRow === null) {
                $cleanOldRow = [];
                foreach ($oldRow as $column => $value) {
                    if ($column === 'row_id') {
                        continue;
                    }
                    $cleanOldRow[$column] = $value;
                }

                if (!empty($cleanOldRow)) {
                    $cleanOldRow['row_id'] = $rowId;
                    $oldChanges[] = $cleanOldRow;
                }
                continue;
            }

            /* compare columns */
            $columns = array_unique(
                array_merge(
                    array_keys($oldRow),
                    array_keys($newRow)
                )
            );

            $oldChangedRow = [];
            $newChangedRow = [];

            foreach ($columns as $column) {
                if ($column === 'row_id') {
                    continue;
                }

                $oldValue = $oldRow[$column] ?? null;
                $newValue = $newRow[$column] ?? null;

                if ($oldValue == $newValue) {
                    continue;
                }

                $oldChangedRow[$column] = $oldValue;
                $newChangedRow[$column] = $newValue;
            }

            if (!empty($oldChangedRow) || !empty($newChangedRow)) {
                $oldChangedRow['row_id'] = $rowId;
                $newChangedRow['row_id'] = $rowId;

                $oldChanges[] = $oldChangedRow;
                $newChanges[] = $newChangedRow;
            }
        }

        return [$oldChanges, $newChanges];
    }
}