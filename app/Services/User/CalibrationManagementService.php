<?php

namespace App\Services\User;

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

class CalibrationManagementService
{
    /* store process record */
    public static function storeCalibrationManagementData(Request $request)
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

            $recordNumber = ProcessRecordService::getGeneratedRecordNumber(
                $request->process_id
            );

            $processData = is_array($request->process_data)
                ? $request->process_data
                : [];

            // $recordNumberFound = false;

            // foreach ($processData as &$field) {
            //     if (
            //         is_array($field) &&
            //         ($field['key'] ?? null) === 'recordNumber'
            //     ) {
            //         $field['value'] = $recordNumber;
            //         $recordNumberFound = true;
            //         break;
            //     }
            // }

            unset($field);

            // if (!$recordNumberFound) {
            //     $processData[] = [
            //         'key' => 'recordNumber',
            //         'label' => 'Record Number',
            //         'value' => $recordNumber,
            //     ];
            // }

            $processRecord = ProcessRecord::create([
                'process_id' => $request->process_id,
                'is_child' => true,
                'parent_id' => $request->parent_id,
                'stage_id' => $request->stage_id,
                'department_id' => $request->department_id,
                'initiator_id' => $request->initiator_id,
                'short_description' => $request->short_description,
                'initiation_date' => $request->initiation_date,
                'process_data' => $processData,
            ]);

            /* Store grid data */
            if (
                $request->has('gridData') &&
                is_array($request->gridData) &&
                !empty($request->gridData)
            ) {
                $gridData = array_map(function ($row) {
                    unset($row['_rowId'], $row['row_id']);

                    return $row;
                }, $request->gridData);

                $gridRecord = GridRecord::create([
                    'process_record_id' => $processRecord->id,
                    'grid_data' => $gridData,
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
                'process_data' => $processData,
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
    public static function getCalibrationManagementRecord($id)
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
    public static function updateCalibrationManagementRecord(
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

                $gridData = array_map(function ($row) {
                    unset(
                        $row['_rowId'],
                        $row['row_id']
                    );

                    return $row;
                }, $gridData);

                $gridRecord = GridRecord::where(
                    'process_record_id',
                    $processRecord->id
                )->first();

                if ($gridRecord) {
                    $oldGridData = is_array($gridRecord->grid_data)
                        ? $gridRecord->grid_data
                        : [];

                    [$gridOldChanges, $gridNewChanges] =
                        self::getGridDataChanges(
                            $oldGridData,
                            $gridData
                        );

                    if (
                        !empty($gridOldChanges) ||
                        !empty($gridNewChanges)
                    ) {
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

            info(
                'Error in CalibrationManagementService@updateCalibrationManagementRecord',
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

    private static function processDataHasKey(
        array $processData,
        string $key
    ): bool {
        foreach ($processData as $field) {
            if (
                is_array($field) &&
                ($field['key'] ?? null) === $key
            ) {
                return true;
            }
        }

        return false;
    }

    /* process data diff */
    private static function getProcessDataChanges(
        array $oldData,
        array $newData
    ): array {
        $old = [];
        $new = [];

        $oldFields = self::indexProcessData($oldData);
        $newFields = self::indexProcessData($newData);

        foreach (
            array_unique(
                array_merge(
                    array_keys($oldFields),
                    array_keys($newFields)
                )
            ) as $key
        ) {
            $oldItem = $oldFields[$key] ?? null;
            $newItem = $newFields[$key] ?? null;

            $oldVal = self::extractProcessFieldValue(
                $oldItem['value'] ?? null
            );

            $newVal = self::extractProcessFieldValue(
                $newItem['value'] ?? null
            );

            if (self::processValuesAreSame($oldVal, $newVal)) {
                continue;
            }

            $old[] = [
                'key' => $oldItem['key'] ?? $key,
                'label' => $oldItem['label']
                    ?? $newItem['label']
                    ?? $key,
                'value' => $oldVal,
            ];

            $new[] = [
                'key' => $newItem['key'] ?? $key,
                'label' => $newItem['label']
                    ?? $oldItem['label']
                    ?? $key,
                'value' => $newVal,
            ];
        }

        return [$old, $new];
    }

    /* unwrap process field values */
    private static function extractProcessFieldValue($value)
    {
        if (is_object($value)) {
            $value = method_exists($value, 'toArray')
                ? $value->toArray()
                : (array) $value;
        }

        if (is_array($value) && isset($value['name'])) {
            return $value['name'];
        }

        if (is_array($value) && empty($value)) {
            return null;
        }

        return $value;
    }

    /* compare process values */
    private static function processValuesAreSame($old, $new): bool
    {
        $oldEmpty = $old === null || $old === '' || $old === [];
        $newEmpty = $new === null || $new === '' || $new === [];

        if ($oldEmpty && $newEmpty) {
            return true;
        }

        return $old == $new;
    }

    /* index process data by key */
    private static function indexProcessData(array $data): array
    {
        $result = [];

        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $key = $item['key'] ?? null;

            if ($key) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /* grid data diff */
    private static function getGridDataChanges(
        array $oldData,
        array $newData
    ): array {
        $oldChanges = [];
        $newChanges = [];

        $oldRows = [];
        $newRows = [];

        foreach ($oldData as $row) {
            if (!is_array($row)) {
                continue;
            }

            $oldRows[] = self::removeGridTechnicalFields($row);
        }

        foreach ($newData as $row) {
            if (!is_array($row)) {
                continue;
            }

            $newRows[] = self::removeGridTechnicalFields($row);
        }

        $maxRows = max(
            count($oldRows),
            count($newRows)
        );

        for ($index = 0; $index < $maxRows; $index++) {
            $rowNumber = $index + 1;

            $oldRow = $oldRows[$index] ?? null;
            $newRow = $newRows[$index] ?? null;

            if ($oldRow === null && $newRow !== null) {
                if (!empty($newRow)) {
                    $newChanges[] = [
                        'row' => $rowNumber,
                        'fields' => self::convertGridRowToAuditFields(
                            $newRow
                        ),
                    ];
                }

                continue;
            }

            if ($oldRow !== null && $newRow === null) {
                if (!empty($oldRow)) {
                    $oldChanges[] = [
                        'row' => $rowNumber,
                        'fields' => self::convertGridRowToAuditFields(
                            $oldRow
                        ),
                    ];
                }

                continue;
            }

            $columns = array_unique(
                array_merge(
                    array_keys($oldRow),
                    array_keys($newRow)
                )
            );

            $oldChangedFields = [];
            $newChangedFields = [];

            foreach ($columns as $column) {
                $oldColumn = self::extractGridColumnValue(
                    $oldRow[$column] ?? null
                );

                $newColumn = self::extractGridColumnValue(
                    $newRow[$column] ?? null
                );

                if (
                    self::processValuesAreSame(
                        $oldColumn,
                        $newColumn
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

            if (
                !empty($oldChangedFields) ||
                !empty($newChangedFields)
            ) {
                $oldChanges[] = [
                    'row' => $rowNumber,
                    'fields' => $oldChangedFields,
                ];

                $newChanges[] = [
                    'row' => $rowNumber,
                    'fields' => $newChangedFields,
                ];
            }
        }

        return [$oldChanges, $newChanges];
    }

    private static function convertGridRowToAuditFields(
        array $row
    ): array {
        $fields = [];

        foreach ($row as $key => $column) {
            $value = self::extractGridColumnValue($column);

            if (self::processValuesAreSame(null, $value)) {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => self::getGridColumnLabel(
                    $column,
                    null,
                    $key
                ),
                'value' => $value,
            ];
        }

        return $fields;
    }

    private static function getGridColumnLabel(
        $oldColumn,
        $newColumn,
        $fallback
    ): string {
        if (
            is_array($newColumn) &&
            isset($newColumn['label'])
        ) {
            return $newColumn['label'];
        }

        if (
            is_array($oldColumn) &&
            isset($oldColumn['label'])
        ) {
            return $oldColumn['label'];
        }

        return self::formatFieldLabel($fallback);
    }


    /* unwrap grid column value */
    private static function extractGridColumnValue($value)
    {
        if (
            is_array($value) &&
            array_key_exists('value', $value) &&
            array_key_exists('label', $value)
        ) {
            return $value['value'];
        }

        if (is_array($value) && isset($value['name'])) {
            return $value['name'];
        }

        return $value;
    }

    private static function removeGridTechnicalFields(array $row): array
    {
        foreach (
            [
                'id',
                '_rowId',
                'row_id',
                'grid_record_id',
                'process_record_id',
                'created_at',
                'updated_at',
            ] as $field
        ) {
            unset($row[$field]);
        }

        return $row;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(
            ['_', '-'],
            ' ',
            (string) $key
        );

        $key = str_replace(
            '/',
            ' / ',
            $key
        );

        return ucwords(
            strtolower(
                trim($key)
            )
        );
    }

    /* compare grid values */
    private static function gridValuesAreSame($old, $new): bool
    {
        if (is_object($old)) {
            $old = method_exists($old, 'toArray')
                ? $old->toArray()
                : (array) $old;
        }

        if (is_object($new)) {
            $new = method_exists($new, 'toArray')
                ? $new->toArray()
                : (array) $new;
        }

        return $old === $new || $old == $new;
    }

    private static function indexGridRow(array $row): array
    {
        $result = [];

        foreach ($row as $key => $column) {
            if (!is_array($column)) {
                continue;
            }

            if (!isset($column['label'])) {
                continue;
            }

            $result[$key] = $column;
        }

        return $result;
    }

    /* move process record stage */
    public static function moveStage(
        RecordActivityRequest $request,
        $id
    ) {
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
            if (
                !$activity->fromStage ||
                $activity->fromStage->process_id !=
                $processRecord->process_id
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Selected activity does not belong to this process.',
                    422
                );
            }

            /* make sure stage belongs to current process */
            if (
                !$activity->toStage ||
                $activity->toStage->process_id !=
                $processRecord->process_id
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Invalid target stage for this process.',
                    422
                );
            }

            $currentStage = $processRecord->stage;
            $targetStage = $activity->toStage;

            /* verify activity user */
            $user = User::find($request->user_id);

            if (
                !$user ||
                $user->email !== $request->email ||
                !Hash::check(
                    $request->password,
                    $user->password
                )
            ) {
                DB::rollBack();

                return ResponseHelper::error(
                    'Invalid email or password.',
                    422
                );
            }

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
}