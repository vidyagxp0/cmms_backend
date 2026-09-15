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

            unset($field);

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
                $gridData = self::cleanGridDataPayload(
                    $request->gridData
                );

                $gridRecord = GridRecord::create([
                    'process_record_id' => $processRecord->id,
                    'grid_data' => $gridData,
                ]);

                /* Grid audit - only rows containing actual values */
                $gridAuditData = self::getGridAuditData($gridData);

                if (!empty($gridAuditData)) {
                    UserAuditHelper::log(
                        'Grid Record',
                        'Created',
                        'Grid data created successfully.',
                        $gridRecord->id,
                        null,
                        [
                            'process_record_id' => $gridRecord->process_record_id,
                            'grid_data' => $gridAuditData,
                        ],
                        GridRecord::class
                    );
                }
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

            /* get data from record activity history for QA verification */
            $qaVerifiedAt = RecordActivityHistory::where(
                'process_record_id',
                $processRecord->id
            )
                ->whereHas('activity', function ($query) {
                    $query->where('name', 'QA Verification Complete');
                })
                ->latest('performed_at')
                ->value('performed_at');

            /* pass data here */
            $processRecord->qaVerifiedAt = $qaVerifiedAt;

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

                /*
                 * Attachment fields are managed by the attachment endpoint.
                 * Do not clear an existing attachment when another stage sends
                 * process data with an empty attachment value.
                 */
                $newProcessData = self::preserveExistingAttachmentValues(
                    $oldProcessData,
                    $requestProcessData
                );

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

                $gridData = self::cleanGridDataPayload($gridData);

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

                    /* Audit only real rows, never the technical grid definition. */
                    $gridAuditData = self::getGridAuditData($gridData);

                    if (!empty($gridAuditData)) {
                        UserAuditHelper::log(
                            'Grid Record',
                            'Created',
                            'Grid data created successfully.',
                            $gridRecord->id,
                            null,
                            [
                                'process_record_id' => $processRecord->id,
                                'grid_data' => $gridAuditData,
                            ],
                            GridRecord::class
                        );
                    }
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

    /*
     * Preserve attachment values already stored on the record.
     * Attachment upload/delete is handled separately from process_data.
     */
    private static function preserveExistingAttachmentValues(
        array $oldData,
        array $newData
    ): array {
        $oldFields = self::indexProcessData($oldData);
        $newFields = self::indexProcessData($newData);

        foreach ($oldFields as $key => $oldItem) {
            if (!self::isAttachmentField($key)) {
                continue;
            }

            if (!isset($newFields[$key])) {
                $newData[] = $oldItem;
                continue;
            }

            $newValue = $newFields[$key]['value'] ?? null;

            if (
                $newValue === null ||
                $newValue === '' ||
                $newValue === []
            ) {
                foreach ($newData as &$newItem) {
                    if (
                        is_array($newItem) &&
                        ($newItem['key'] ?? null) === $key
                    ) {
                        $newItem['value'] = $oldItem['value'] ?? null;

                        if (empty($newItem['label'])) {
                            $newItem['label'] =
                                $oldItem['label'] ?? $key;
                        }

                        break;
                    }
                }

                unset($newItem);
            }
        }

        return $newData;
    }

    private static function isAttachmentField($key): bool
    {
        return str_contains(
            strtolower((string) $key),
            'attachment'
        );
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

    /* clean grid payload and remove rows without actual values */
    private static function cleanGridDataPayload(array $gridData): array
    {
        $cleanedGrids = [];

        foreach ($gridData as $grid) {
            if (!is_array($grid)) {
                continue;
            }

            unset(
                $grid['_rowId'],
                $grid['row_id']
            );

            if (
                isset($grid['rows']) &&
                is_array($grid['rows'])
            ) {
                $cleanRows = [];

                foreach ($grid['rows'] as $row) {
                    if (!is_array($row)) {
                        continue;
                    }

                    unset(
                        $row['_rowId'],
                        $row['row_id']
                    );

                    $hasValue = false;

                    foreach ($row as $key => $value) {
                        if (
                            in_array(
                                $key,
                                [
                                    'id',
                                    'grid_record_id',
                                    'process_record_id',
                                    'created_at',
                                    'updated_at',
                                ]
                            )
                        ) {
                            continue;
                        }

                        if (is_array($value)) {
                            if (
                                isset($value['value']) &&
                                !self::processValuesAreSame(
                                    null,
                                    $value['value']
                                )
                            ) {
                                $hasValue = true;
                                break;
                            }

                            continue;
                        }

                        if (!self::processValuesAreSame(null, $value)) {
                            $hasValue = true;
                            break;
                        }
                    }

                    if ($hasValue) {
                        $cleanRows[] = $row;
                    }
                }

                $grid['rows'] = $cleanRows;
            }

            $cleanedGrids[] = $grid;
        }

        return $cleanedGrids;
    }

    /* prepare only meaningful grid rows for audit */
    private static function getGridAuditData(array $gridData): array
    {
        $auditData = [];

        foreach ($gridData as $grid) {
            if (!is_array($grid)) {
                continue;
            }

            $gridName = $grid['name'] ?? null;
            $gridLabel = self::formatGridTitle(
                $gridName,
                $grid['label'] ?? null
            );

            $rows = isset($grid['rows']) && is_array($grid['rows'])
                ? $grid['rows']
                : [];

            foreach ($rows as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $fields = self::convertGridRowToAuditFields($row);

                /* A row without any actual value is not an audit entry. */
                if (empty($fields)) {
                    continue;
                }

                /*
                 * Store only displayable grid information in the audit.
                 * Do not store technical grid name or row-count data.
                 */
                $auditData[] = [
                    'grid_label' => $gridLabel,
                    'row' => $index + 1,
                    'fields' => $fields,
                ];
            }
        }

        return $auditData;
    }

    /* Formats grid name to title (e.g. masterInstrumentsDetails -> Master Instruments Details) */
    public static function formatGridTitle(?string $name, ?string $label = null): string
    {
        if (!empty($label) && !is_numeric($label)) {
            return trim($label);
        }

        if (empty($name)) {
            return 'Grid';
        }

        $known = [
            'calibrationResults'       => 'Calibration Results',
            'masterInstrumentsDetails' => 'Master Instruments Details',
            'masterInstruments'        => 'Master Instruments Details',
            'instrumentDetails'        => 'Instrument Details',
            'testResults'              => 'Test Results',
        ];

        if (isset($known[$name])) {
            return $known[$name];
        }

        $text = preg_replace('/(?<!^)[A-Z]/', ' $0', $name);
        $text = str_replace(['_', '-'], ' ', $text);

        return ucwords(strtolower(trim($text)));
    }

    /* Index grids by name or index */
    private static function indexGridsByName(array $data): array
    {
        $indexed = [];

        foreach ($data as $index => $grid) {
            if (!is_array($grid)) {
                continue;
            }

            $key = isset($grid['name']) && is_string($grid['name']) && trim($grid['name']) !== ''
                ? trim($grid['name'])
                : (string) $index;

            $indexed[$key] = $grid;
        }

        return $indexed;
    }

    /* grid data diff across named grids and row items */
    private static function getGridDataChanges(
        array $oldData,
        array $newData
    ): array {
        $oldChanges = [];
        $newChanges = [];

        $isMultiGrid = false;

        foreach (array_merge($oldData, $newData) as $item) {
            if (
                is_array($item) &&
                (
                    isset($item['name']) ||
                    isset($item['rows'])
                )
            ) {
                $isMultiGrid = true;
                break;
            }
        }

        $oldGrids = self::indexGridsByName(
            $isMultiGrid
                ? $oldData
                : [['name' => null, 'rows' => $oldData]]
        );

        $newGrids = self::indexGridsByName(
            $isMultiGrid
                ? $newData
                : [['name' => null, 'rows' => $newData]]
        );

        $allGridKeys = array_unique(
            array_merge(
                array_keys($oldGrids),
                array_keys($newGrids)
            )
        );

        foreach ($allGridKeys as $gridKey) {
            $oldGrid = $oldGrids[$gridKey] ?? null;
            $newGrid = $newGrids[$gridKey] ?? null;

            $gridName =
                $newGrid['name']
                ?? $oldGrid['name']
                ?? (
                    is_numeric($gridKey)
                        ? null
                        : $gridKey
                );

            $gridLabel = self::formatGridTitle(
                $gridName,
                $newGrid['label']
                    ?? $oldGrid['label']
                    ?? null
            );

            $oldRows =
                isset($oldGrid['rows']) &&
                is_array($oldGrid['rows'])
                    ? array_values($oldGrid['rows'])
                    : [];

            $newRows =
                isset($newGrid['rows']) &&
                is_array($newGrid['rows'])
                    ? array_values($newGrid['rows'])
                    : [];

            $maxRows = max(
                count($oldRows),
                count($newRows)
            );

            for ($index = 0; $index < $maxRows; $index++) {
                $rowNumber = $index + 1;

                $oldRow = $oldRows[$index] ?? null;
                $newRow = $newRows[$index] ?? null;

                $oldRow = is_array($oldRow)
                    ? self::removeGridTechnicalFields($oldRow)
                    : null;

                $newRow = is_array($newRow)
                    ? self::removeGridTechnicalFields($newRow)
                    : null;

                /* row added */
                if ($oldRow === null && $newRow !== null) {
                    $fields = self::convertGridRowToAuditFields(
                        $newRow
                    );

                    if (!empty($fields)) {
                        $newChanges[] = [
                            'grid_label' => $gridLabel,
                            'row' => $rowNumber,
                            'fields' => $fields,
                        ];
                    }

                    continue;
                }

                /* row deleted */
                if ($oldRow !== null && $newRow === null) {
                    $fields = self::convertGridRowToAuditFields(
                        $oldRow
                    );

                    if (!empty($fields)) {
                        $oldChanges[] = [
                            'grid_label' => $gridLabel,
                            'row' => $rowNumber,
                            'fields' => $fields,
                        ];
                    }

                    continue;
                }

                /* existing row */
                $oldFieldsMap = self::mapRowFields($oldRow);
                $newFieldsMap = self::mapRowFields($newRow);

                $changedOld = [];
                $changedNew = [];

                $allCols = array_unique(
                    array_merge(
                        array_keys($oldFieldsMap),
                        array_keys($newFieldsMap)
                    )
                );

                foreach ($allCols as $col) {
                    $oldCol = $oldFieldsMap[$col] ?? null;
                    $newCol = $newFieldsMap[$col] ?? null;

                    $oldVal = self::extractGridColumnValue(
                        $oldCol
                    );

                    $newVal = self::extractGridColumnValue(
                        $newCol
                    );

                    if (
                        self::processValuesAreSame(
                            $oldVal,
                            $newVal
                        )
                    ) {
                        continue;
                    }

                    $label = self::getGridColumnLabel(
                        $oldCol,
                        $newCol,
                        $col
                    );

                    if (!self::isEmptyValue($oldVal)) {
                        $changedOld[] = [
                            'key' => $col,
                            'label' => $label,
                            'value' => $oldVal,
                        ];
                    }

                    if (!self::isEmptyValue($newVal)) {
                        $changedNew[] = [
                            'key' => $col,
                            'label' => $label,
                            'value' => $newVal,
                        ];
                    }
                }

                if (
                    empty($changedOld) &&
                    empty($changedNew)
                ) {
                    continue;
                }

                if (!empty($changedOld)) {
                    $oldChanges[] = [
                        'grid_label' => $gridLabel,
                        'row' => $rowNumber,
                        'fields' => $changedOld,
                    ];
                }

                if (!empty($changedNew)) {
                    $newChanges[] = [
                        'grid_label' => $gridLabel,
                        'row' => $rowNumber,
                        'fields' => $changedNew,
                    ];
                }
            }
        }

        return [
            $oldChanges,
            $newChanges,
        ];
    }

    private static function removeGridTechnicalFields(array $row): array
    {
        foreach ([
            'id',
            '_rowId',
            'row_id',
            'grid_record_id',
            'process_record_id',
            'created_at',
            'updated_at',
            'rows',
        ] as $field) {
            unset($row[$field]);
        }

        return $row;
    }

    private static function isEmptyValue($value): bool
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);

        return false;
    }

    /* normalize a grid row into key => field metadata */
    private static function mapRowFields(?array $row): array
    {
        if (!is_array($row) || empty($row)) {
            return [];
        }

        $map = [];

        /* Row represented as a list of {key,label,value} objects. */
        if (isset($row[0]) && is_array($row[0])) {
            foreach ($row as $field) {
                if (!is_array($field)) {
                    continue;
                }

                $key = $field['key'] ?? $field['Key'] ?? null;

                if ($key === null || $key === '') {
                    continue;
                }

                $map[(string) $key] = [
                    'key' => (string) $key,
                    'label' => $field['label']
                        ?? $field['Label']
                        ?? self::formatFieldLabel($key),
                    'value' => array_key_exists('value', $field)
                        ? $field['value']
                        : ($field['Value'] ?? null),
                ];
            }

            return $map;
        }

        /* Row represented as label => {Key,Label,Value}. */
        foreach ($row as $fieldKey => $field) {
            if (in_array($fieldKey, [
                'id',
                '_rowId',
                'row_id',
                'grid_record_id',
                'process_record_id',
                'created_at',
                'updated_at',
                'rows',
            ], true)) {
                continue;
            }

            if (is_array($field)) {
                $key = $field['key']
                    ?? $field['Key']
                    ?? (is_string($fieldKey) ? $fieldKey : null);

                if ($key === null || $key === '') {
                    continue;
                }

                $map[(string) $key] = [
                    'key' => (string) $key,
                    'label' => $field['label']
                        ?? $field['Label']
                        ?? (is_string($fieldKey)
                            ? self::formatFieldLabel($fieldKey)
                            : self::formatFieldLabel($key)),
                    'value' => array_key_exists('value', $field)
                        ? $field['value']
                        : ($field['Value'] ?? null),
                ];

                continue;
            }

            if (is_string($fieldKey) || is_int($fieldKey)) {
                $map[(string) $fieldKey] = [
                    'key' => (string) $fieldKey,
                    'label' => self::formatFieldLabel($fieldKey),
                    'value' => $field,
                ];
            }
        }

        return $map;
    }

    /* convert one stored grid row into audit-safe fields */
    private static function convertGridRowToAuditFields(?array $row): array
    {
        $fields = [];

        foreach (self::mapRowFields($row) as $key => $field) {
            $value = $field['value'] ?? null;

            if (self::isEmptyValue($value)) {
                continue;
            }

            $fields[] = [
                'key' => $field['key'] ?? $key,
                'label' => $field['label']
                    ?? self::formatFieldLabel($key),
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
                'activity' => $activity->name,
                'stage' => $currentStage?->name,
            ];

            $newValue = [
                'activity' => $activity->name,
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