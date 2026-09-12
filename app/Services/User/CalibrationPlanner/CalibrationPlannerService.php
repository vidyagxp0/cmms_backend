<?php

namespace App\Services\User\CalibrationPlanner;

use App\Helpers\ResponseHelper;
use App\Services\User\ProcessRecordService;
use App\Http\Requests\User\RecordActivityRequest;
use App\Models\ProcessRecord;
use App\Helpers\UserAuditHelper;
use App\Models\Stage;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\Activity;
use App\Models\RecordActivityHistory;
use Illuminate\Support\Facades\Auth;
use App\Models\GridRecord;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CalibrationPlannerService
{
    /* create a new process record */
    public static function storeCalibrationProcessData(Request $request)
    {
        DB::beginTransaction();
        try {
            /* stage must belong to the chosen process */
            $stageExists = Stage::where('id', $request->stage_id)
                ->where('process_id', $request->process_id)->exists();

            if (!$stageExists) {
                DB::rollBack();
                return ResponseHelper::error('Selected stage does not belong to the selected process.', 422);
            }

            /* auto generate record number */
            $recordNumber = ProcessRecordService::getGeneratedRecordNumber($request->process_id);
            $processData = is_array($request->process_data) ? $request->process_data : [];
            $recordNumberFound = false;

            /* inject record number into existing process_data field if present */
            foreach ($processData as &$field) {
                if (is_array($field) && ($field['key'] ?? null) === 'recordNumber') {
                    $field['value'] = $recordNumber;
                    $recordNumberFound = true;
                    break;
                }
            }
            unset($field);

            /* otherwise append a new record number field */
            if (!$recordNumberFound) {
                $processData[] = ['key' => 'recordNumber', 'label' => 'Record Number', 'value' => $recordNumber];
            }

            $processRecord = ProcessRecord::create([
                'process_id' => $request->process_id,
                'stage_id' => $request->stage_id,
                'department_id' => $request->department_id,
                'initiator_id' => $request->initiator_id,
                'short_description' => $request->short_description,
                'initiation_date' => $request->initiation_date,
                'process_data' => $processData,
            ]);

            /* store grid rows if provided */
            if ($request->has('gridData') && is_array($request->gridData) && !empty($request->gridData)) {
                /* strip temp row identifiers before saving */
                $gridData = array_map(function ($row) {
                    unset($row['_rowId'], $row['row_id']);
                    return $row;
                }, $request->gridData);

                $gridRecord = GridRecord::create([
                    'process_record_id' => $processRecord->id,
                    'grid_data' => $gridData,
                ]);

                /* audit: grid created */
                UserAuditHelper::log(
                    'Grid Record',
                    'Created',
                    'Grid data created successfully.',
                    $gridRecord->id,
                    null,
                    ['process_record_id' => $gridRecord->process_record_id, 'grid_data' => $gridRecord->grid_data],
                    GridRecord::class
                );
            }

            /* audit: process record created */
            UserAuditHelper::log(
                'Process Record',
                'Created',
                'Process record created successfully.',
                $processRecord->id,
                null,
                ['process_data' => $processData],
                ProcessRecord::class
            );

            DB::commit();
            $processRecord->load(['process', 'stage', 'department', 'initiator']);

            return ResponseHelper::success($processRecord, 'Process record created successfully.', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error('Failed to create process record.', 500);
        }
    }

    /* fetch a single process record with related data */
    public static function getCalibrationPlannerRecord($id)
    {
        try {
            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
                'gridRecords',
                'checklistRecords',
            ])->findOrFail($id);

            return ResponseHelper::success($processRecord, 'Process record fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Process record not found.', 404);
        }
    }

    /* update an existing process record and log the diff */
    public static function updateCalibrationPlannerRecord(
        Request $request,
        $id
    ) {
        DB::beginTransaction();

        try {
            $processRecord = ProcessRecord::findOrFail($id);

            $processId = $request->has('process_id')
                ? $request->process_id
                : $processRecord->process_id;

            $stageId = $processRecord->stage_id;

            $stageExists = Stage::where('id', $stageId)
                ->where('process_id', $processId)
                ->exists();

            if (!$stageExists) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Current stage does not belong to the selected process.',
                    422
                );
            }

            $oldValue = [];
            $newValue = [];
            $updateData = [];

            $requestProcessData = is_array($request->process_data)
                ? $request->process_data
                : [];

            $hasShortDescriptionInProcessData =
                self::processDataHasKey(
                    $requestProcessData,
                    'short_description'
                );

            $scalarFieldsMap = [
                'process_id' => [
                    'key' => 'process_id',
                    'label' => 'Process',
                    'resolver' => fn($value) =>
                        \App\Models\Process::where('id', $value)->value('name'),
                ],

                'department_id' => [
                    'key' => 'department_id',
                    'label' => 'Department',
                    'resolver' => fn($value) =>
                        \App\Models\Department::where('id', $value)->value('name'),
                ],

                'initiator_id' => [
                    'key' => 'initiator_id',
                    'label' => 'Initiator',
                    'resolver' => fn($value) =>
                        User::where('id', $value)->value('name'),
                ],
            ];

            foreach ($scalarFieldsMap as $field => $meta) {
                if (!$request->has($field)) {
                    continue;
                }

                $oldFieldValue = $processRecord->{$field};
                $newFieldValue = $request->{$field};

                if ($oldFieldValue == $newFieldValue) {
                    continue;
                }

                $oldValue['process_data'][] = [
                    'key' => $meta['key'],
                    'label' => $meta['label'],
                    'value' => $oldFieldValue
                        ? $meta['resolver']($oldFieldValue)
                        : null,
                ];

                $newValue['process_data'][] = [
                    'key' => $meta['key'],
                    'label' => $meta['label'],
                    'value' => $newFieldValue
                        ? $meta['resolver']($newFieldValue)
                        : null,
                ];

                $updateData[$field] = $newFieldValue;
            }

            if (
                $request->has('short_description') &&
                $request->short_description != $processRecord->short_description
            ) {
                $updateData['short_description'] =
                    $request->short_description;

                if (!$hasShortDescriptionInProcessData) {
                    $oldValue['process_data'][] = [
                        'key' => 'short_description',
                        'label' => 'Short Description',
                        'value' => $processRecord->short_description,
                    ];

                    $newValue['process_data'][] = [
                        'key' => 'short_description',
                        'label' => 'Short Description',
                        'value' => $request->short_description,
                    ];
                }
            }

            /* process data */
            if ($request->has('process_data')) {
                $oldProcessData = is_array($processRecord->process_data)
                    ? $processRecord->process_data
                    : [];

                $newProcessData = $requestProcessData;

                [$processOldChanges, $processNewChanges] =
                    self::getProcessDataChanges(
                        $oldProcessData,
                        $newProcessData
                    );

                if (!empty($processOldChanges)) {
                    $oldValue['process_data'] = array_merge(
                        $oldValue['process_data'] ?? [],
                        $processOldChanges
                    );
                }

                if (!empty($processNewChanges)) {
                    $newValue['process_data'] = array_merge(
                        $newValue['process_data'] ?? [],
                        $processNewChanges
                    );
                }

                if ($oldProcessData != $newProcessData) {
                    $updateData['process_data'] = $newProcessData;
                }
            }

            if (!empty($updateData)) {
                $processRecord->update($updateData);
            }

            if (!empty($newValue) || !empty($oldValue)) {
                UserAuditHelper::log(
                    'Process Record',
                    'Updated',
                    'Process record updated successfully.',
                    $processRecord->id,
                    !empty($oldValue) ? $oldValue : null,
                    !empty($newValue) ? $newValue : null,
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

                if ($gridRecord) {
                    $oldGridData = is_array($gridRecord->grid_data)
                        ? $gridRecord->grid_data
                        : [];

                    /*
                    * Compare rows before removing row identifiers.
                    * This allows the audit logic to detect the correct row.
                    */
                    $gridChanges = self::getGridDataChanges(
                        $oldGridData,
                        $gridData
                    );

                    /* deleted rows */
                    if (!empty($gridChanges['deleted'])) {
                        UserAuditHelper::log(
                            'Grid Record',
                            'Deleted',
                            'Grid row deleted successfully.',
                            $gridRecord->id,
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridChanges['deleted'],
                            ],
                            null,
                            GridRecord::class
                        );
                    }

                    /* updated rows */
                    if (
                        !empty($gridChanges['updated_old']) ||
                        !empty($gridChanges['updated_new'])
                    ) {
                        UserAuditHelper::log(
                            'Grid Record',
                            'Updated',
                            'Grid data updated successfully.',
                            $gridRecord->id,
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridChanges['updated_old'],
                            ],
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridChanges['updated_new'],
                            ],
                            GridRecord::class
                        );
                    }

                    /* created rows */
                    if (!empty($gridChanges['created'])) {
                        UserAuditHelper::log(
                            'Grid Record',
                            'Created',
                            'Grid row created successfully.',
                            $gridRecord->id,
                            null,
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridChanges['created'],
                            ],
                            GridRecord::class
                        );
                    }

                    /* remove technical fields only before saving */
                    $gridDataForSave = array_map(function ($row) {
                        unset(
                            $row['_rowId'],
                            $row['row_id']
                        );

                        return $row;
                    }, $gridData);

                    if ($oldGridData != $gridDataForSave) {
                        $gridRecord->update([
                            'grid_data' => $gridDataForSave,
                        ]);
                    }
                } elseif (!empty($gridData)) {
                    $gridDataForSave = array_map(function ($row) {
                        unset(
                            $row['_rowId'],
                            $row['row_id']
                        );

                        return $row;
                    }, $gridData);

                    $gridRecord = GridRecord::create([
                        'process_record_id' => $processRecord->id,
                        'grid_data' => $gridDataForSave,
                    ]);

                    UserAuditHelper::log(
                        'Grid Record',
                        'Created',
                        'Grid data created successfully.',
                        $gridRecord->id,
                        null,
                        [
                            'process_record_id' => $processRecord->id,
                            'grid_data' => $gridDataForSave,
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

            info(
                'Error in CalibrationPlannerService@updateCalibrationPlannerRecord',
                [
                    'error' => $e->getMessage(),
                ]
            );

            return ResponseHelper::error(
                'Failed to update process record.',
                500
            );
        }
    }

    /* check if a process_data key already exists */
    private static function processDataHasKey(array $processData, string $key): bool
    {
        foreach ($processData as $field) {
            if (is_array($field) && ($field['key'] ?? null) === $key)
                return true;
        }
        return false;
    }

    /* build old/new audit pairs for changed process_data fields */
    private static function getProcessDataChanges(array $oldData, array $newData): array
    {
        $old = [];
        $new = [];
        $oldFields = self::indexProcessData($oldData);
        $newFields = self::indexProcessData($newData);

        foreach (array_unique(array_merge(array_keys($oldFields), array_keys($newFields))) as $key) {
            $oldItem = $oldFields[$key] ?? null;
            $newItem = $newFields[$key] ?? null;
            $oldVal = self::extractProcessFieldValue($oldItem['value'] ?? null);
            $newVal = self::extractProcessFieldValue($newItem['value'] ?? null);

            if (self::processValuesAreSame($oldVal, $newVal))
                continue;

            $old[] = ['key' => $oldItem['key'] ?? $key, 'label' => $oldItem['label'] ?? $newItem['label'] ?? $key, 'value' => $oldVal];
            $new[] = ['key' => $newItem['key'] ?? $key, 'label' => $newItem['label'] ?? $oldItem['label'] ?? $key, 'value' => $newVal];
        }

        return [$old, $new];
    }

    /* pull a plain scalar out of an object/array field value */
    private static function extractProcessFieldValue($value)
    {
        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }
        if (is_array($value) && isset($value['name']))
            return $value['name'];
        if (is_array($value) && empty($value))
            return null;

        return $value;
    }

    /* treat null/''/[] as equivalent "empty" values when comparing */
    private static function processValuesAreSame($old, $new): bool
    {
        $oldEmpty = $old === null || $old === '' || $old === [];
        $newEmpty = $new === null || $new === '' || $new === [];

        if ($oldEmpty && $newEmpty)
            return true;

        return $old == $new;
    }

    /* index process_data rows by their key */
    private static function indexProcessData(array $data): array
    {
        $result = [];
        foreach ($data as $item) {
            if (!is_array($item))
                continue;
            $key = $item['key'] ?? null;
            if ($key)
                $result[$key] = $item;
        }
        return $result;
    }

    /* grid data diff */
    private static function getGridDataChanges(
        array $oldData,
        array $newData
    ): array {
        $oldRows = [];
        $newRows = [];

        foreach ($oldData as $row) {
            if (is_array($row)) {
                $oldRows[] = self::removeGridTechnicalFields($row);
            }
        }

        foreach ($newData as $row) {
            if (is_array($row)) {
                $newRows[] = self::removeGridTechnicalFields($row);
            }
        }

        /* index rows by stable identity */
        $oldIndexed = self::indexGridRowsForAudit($oldRows);
        $newIndexed = self::indexGridRowsForAudit($newRows);

        $created = [];
        $updatedOld = [];
        $updatedNew = [];
        $deleted = [];

        /* check existing and deleted rows */
        foreach ($oldIndexed as $identity => $oldRowData) {
            $oldRow = $oldRowData['row'];
            $oldRowNumber = $oldRowData['row_number'];

            /* row deleted */
            if (!isset($newIndexed[$identity])) {
                if (!empty($oldRow)) {
                    $deleted[] = [
                        'row' => $oldRowNumber,
                        'fields' => self::convertGridRowToAuditFields(
                            $oldRow
                        ),
                    ];
                }

                continue;
            }

            $newRow = $newIndexed[$identity]['row'];
            $newRowNumber = $newIndexed[$identity]['row_number'];

            $columns = array_unique(
                array_merge(
                    array_keys($oldRow),
                    array_keys($newRow)
                )
            );

            $oldChangedFields = [];
            $newChangedFields = [];

            foreach ($columns as $column) {
                /* never audit this internal calibration field */
                if (self::isExcludedGridAuditField($column)) {
                    continue;
                }

                $oldColumn = self::extractGridColumnValue(
                    $oldRow[$column] ?? null
                );

                $newColumn = self::extractGridColumnValue(
                    $newRow[$column] ?? null
                );

                /*
                * Compare dates using normalized format.
                *
                * 13/09/2026 == 2026-09-13
                *
                * Therefore a date format conversion alone
                * will not create an audit change.
                */
                $oldCompareValue = self::normalizeGridDateValue(
                    $oldColumn
                );

                $newCompareValue = self::normalizeGridDateValue(
                    $newColumn
                );

                if (
                    self::processValuesAreSame(
                        $oldCompareValue,
                        $newCompareValue
                    )
                ) {
                    continue;
                }

                $label = self::getGridColumnLabel(
                    $oldRow[$column] ?? null,
                    $newRow[$column] ?? null,
                    $column
                );

                $oldChangedFields[] = [
                    'key' => $column,
                    'label' => $label,
                    'value' => $oldColumn,
                ];

                $newChangedFields[] = [
                    'key' => $column,
                    'label' => $label,
                    'value' => $newColumn,
                ];
            }

            /* row updated */
            if (
                !empty($oldChangedFields) ||
                !empty($newChangedFields)
            ) {
                $updatedOld[] = [
                    'row' => $newRowNumber,
                    'fields' => $oldChangedFields,
                ];

                $updatedNew[] = [
                    'row' => $newRowNumber,
                    'fields' => $newChangedFields,
                ];
            }
        }

        /* check newly created rows */
        foreach ($newIndexed as $identity => $newRowData) {
            if (isset($oldIndexed[$identity])) {
                continue;
            }

            $newRow = $newRowData['row'];
            $newRowNumber = $newRowData['row_number'];

            if (!empty($newRow)) {
                $created[] = [
                    'row' => $newRowNumber,
                    'fields' => self::convertGridRowToAuditFields(
                        $newRow
                    ),
                ];
            }
        }

        return [
            'created' => $created,
            'updated_old' => $updatedOld,
            'updated_new' => $updatedNew,
            'deleted' => $deleted,
        ];
    }


    /* normalize grid date value for comparison */
    private static function normalizeGridDateValue($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        $value = trim($value);

        /* dd/mm/yyyy -> yyyy-mm-dd */
        if (
            preg_match(
                '/^\d{2}\/\d{2}\/\d{4}$/',
                $value
            )
        ) {
            $date = \DateTime::createFromFormat(
                'd/m/Y',
                $value
            );

            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        /* yyyy-mm-dd remains yyyy-mm-dd */
        if (
            preg_match(
                '/^\d{4}-\d{2}-\d{2}$/',
                $value
            )
        ) {
            return $value;
        }

        return $value;
    }


    /* build identity => row map for audit comparison */
    private static function indexGridRowsForAudit(array $rows): array
    {
        $result = [];
        foreach ($rows as $index => $row) {
            $identity = self::getGridRowIdentity($row) ?? 'row_' . ($index + 1);
            $result[$identity] = ['row' => $row, 'row_number' => $index + 1];
        }
        return $result;
    }

    /* prefer equipment id or row id as a stable row identity, else null */
    private static function getGridRowIdentity(array $row): ?string
    {
        if (isset($row['equipmentInstrumentId'])) {
            $value = self::extractGridColumnValue($row['equipmentInstrumentId']);
            if ($value !== null && $value !== '')
                return 'equipment_' . (string) $value;
        }
        if (isset($row['row_id']))
            return 'row_' . (string) $row['row_id'];
        if (isset($row['_rowId']))
            return 'row_' . (string) $row['_rowId'];

        return null;
    }

    /* fields skipped from grid audit logs */
    private static function isExcludedGridAuditField($key): bool
    {
        return in_array($key, ['calibrationFrequencyStartDate'], true);
    }

    /* turn a full grid row into audit-log field entries */
    private static function convertGridRowToAuditFields(array $row): array
    {
        $fields = [];
        foreach ($row as $key => $column) {
            if (self::isExcludedGridAuditField($key))
                continue;

            $value = self::extractGridColumnValue($column);
            if (self::processValuesAreSame(null, $value))
                continue;

            $fields[] = ['key' => $key, 'label' => self::getGridColumnLabel($column, null, $key), 'value' => $value];
        }
        return $fields;
    }

    /* pick a human label for a grid column, falling back to a formatted key */
    private static function getGridColumnLabel($oldColumn, $newColumn, $fallback): string
    {
        if (is_array($newColumn) && isset($newColumn['label']))
            return $newColumn['label'];
        if (is_array($oldColumn) && isset($oldColumn['label']))
            return $oldColumn['label'];

        return self::formatFieldLabel($fallback);
    }

    /* pull a plain scalar out of a grid column value */
    private static function extractGridColumnValue($value)
    {
        if (is_array($value) && array_key_exists('value', $value) && array_key_exists('label', $value)) {
            return $value['value'];
        }
        if (is_array($value) && isset($value['name']))
            return $value['name'];

        return $value;
    }

    /* strip internal/db fields before diffing or saving a grid row */
    private static function removeGridTechnicalFields(array $row): array
    {
        foreach (['id', '_rowId', 'row_id', 'grid_record_id', 'process_record_id', 'created_at', 'updated_at'] as $field) {
            unset($row[$field]);
        }
        return $row;
    }

    /* turn a raw key like "some_field" into "Some Field" */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', (string) $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    /* move a process record from its current stage to the next via an activity */
    public static function moveStage(RecordActivityRequest $request, $id)
    {
        DB::beginTransaction();
        try {
            $processRecord = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->findOrFail($id);

            /* activity must be active and valid for the record's current stage */
            $activity = Activity::with(['fromStage', 'toStage'])
                ->where('id', $request->activity_id)
                ->where('is_active', true)
                ->where('from_stage', $processRecord->stage_id)
                ->first();

            if (!$activity) {
                DB::rollBack();
                return ResponseHelper::error('Selected activity is not available for the current stage.', 422);
            }

            /* activity's source stage must belong to this process */
            if (!$activity->fromStage || $activity->fromStage->process_id != $processRecord->process_id) {
                DB::rollBack();
                return ResponseHelper::error('Selected activity does not belong to this process.', 422);
            }

            /* activity's target stage must belong to this process */
            if (!$activity->toStage || $activity->toStage->process_id != $processRecord->process_id) {
                DB::rollBack();
                return ResponseHelper::error('Invalid target stage for this process.', 422);
            }

            $currentStage = $processRecord->stage;
            $targetStage = $activity->toStage;
            $user = User::find($request->user_id);

            /* re-verify the acting user's credentials before moving stage */
            if (!$user || $user->email !== $request->email || !Hash::check($request->password, $user->password)) {
                DB::rollBack();
                return ResponseHelper::error('Invalid email or password.', 422);
            }

            /* record the activity performed */
            RecordActivityHistory::create([
                'process_record_id' => $processRecord->id,
                'activity_id' => $activity->id,
                'performed_by' => Auth::id(),
                'stage_id' => $currentStage->id,
                'target_stage' => $targetStage->id,
                'comment' => $request->comment,
                'performed_at' => now(),
            ]);

            $processRecord->stage_id = $targetStage->id;
            $processRecord->save();

            $description = $request->filled('comment') ? $request->comment : 'Process record stage updated successfully.';

            /* audit: stage change */
            UserAuditHelper::log(
                'Process Record',
                'Activity Performed',
                $description,
                $processRecord->id,
                ['stage' => $currentStage?->name],
                ['stage' => $targetStage?->name],
                ProcessRecord::class
            );

            DB::commit();
            $processRecord->load(['process', 'stage', 'department', 'initiator']);

            return ResponseHelper::success([
                'record' => $processRecord,
                'activity' => [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'from_stage' => $currentStage->name,
                    'to_stage' => $targetStage->name,
                ],
            ], 'Process record stage updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }
}