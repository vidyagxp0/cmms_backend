<?php

namespace App\Services\User;

use App\Helpers\FieldLabelHelper;
use Illuminate\Http\Request;
use App\Helpers\ResponseHelper;
use App\Models\Audit;
use App\Models\GridRecord;
use App\Models\ChecklistRecord;
use App\Models\ProcessRecord;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;

class UserAuditService
{
    /* get process record audits */
    public static function getProcessRecordAudits($id, Request $request)
    {
        try {
            /* pagination */
            $perPage = 10;
            $page = max((int) $request->input('page', 1), 1);

            /* search */
            $search = trim((string) $request->input('search', ''));

            /* date filters */
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $processRecord = ProcessRecord::with([
                'process',
                'stage',
                'department',
                'initiator',
            ])->findOrFail($id);

            /* child record ids */
            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id');
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)->pluck('id');

            $processAuditQuery = Audit::with('user')
                ->where('record_id', $id)
                ->where('model', ProcessRecord::class);

            /* grid audit */
            $gridAuditQuery = Audit::with('user')
                ->where('model', GridRecord::class)
                ->whereIn('record_id', $gridRecordIds);

            /* checklist audit */
            $checklistAuditQuery = Audit::with('user')
                ->where('model', ChecklistRecord::class)
                ->whereIn('record_id', $checklistRecordIds);

            if (!empty($fromDate)) {
                $from = Carbon::parse($fromDate)->startOfDay();
                $processAuditQuery->where('created_at', '>=', $from);
                $gridAuditQuery->where('created_at', '>=', $from);
                $checklistAuditQuery->where('created_at', '>=', $from);
            }

            if (!empty($toDate)) {
                $to = Carbon::parse($toDate)->endOfDay();
                $processAuditQuery->where('created_at', '<=', $to);
                $gridAuditQuery->where('created_at', '<=', $to);
                $checklistAuditQuery->where('created_at', '<=', $to);
            }

            $processAudits = $processAuditQuery->get();
            $gridAudits = $gridAuditQuery->get();
            $checklistAudits = $checklistAuditQuery->get();

            /* merge all audits */
            $audits = $processAudits
                ->concat($gridAudits)
                ->concat($checklistAudits)
                ->sortByDesc(function ($audit) {
                    return $audit->created_at
                        ? Carbon::parse($audit->created_at)->timestamp
                        : 0;
                })
                ->values();

            $auditRows = [];

            foreach ($audits as $audit) {
                $oldValue = self::prepareValue($audit->old_value);
                $newValue = self::prepareValue($audit->new_value);

                /* activity audits */
                if (strtolower(trim($audit->action)) === 'activity performed') {
                    $activityRow = self::prepareActivityAudit($audit, $oldValue, $newValue);

                    if ($activityRow) {
                        $auditRows[] = $activityRow;
                    }

                    continue;
                }

                if ($audit->model === ProcessRecord::class) {
                    foreach (self::prepareProcessAudit($audit, $oldValue, $newValue) as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }

                if ($audit->model === GridRecord::class) {
                    foreach (self::prepareGridAudit($audit, $oldValue, $newValue) as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }

                if ($audit->model === ChecklistRecord::class) {
                    foreach (self::prepareChecklistAudit($audit, $oldValue, $newValue) as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }
            }

            if ($search !== '') {
                $searchLower = strtolower($search);

                $auditRows = array_values(array_filter(
                    $auditRows,
                    function ($row) use ($searchLower) {
                        return str_contains(
                            strtolower((string) ($row['module'] ?? '')),
                            $searchLower
                        ) || str_contains(
                            strtolower((string) ($row['responsible_person'] ?? '')),
                            $searchLower
                        ) || str_contains(
                            strtolower((string) ($row['field'] ?? '')),
                            $searchLower
                        );
                    }
                ));
            }

            usort($auditRows, function ($a, $b) {
                $dateA = !empty($a['created_at']) ? Carbon::parse($a['created_at'])->timestamp : 0;
                $dateB = !empty($b['created_at']) ? Carbon::parse($b['created_at'])->timestamp : 0;

                return $dateB <=> $dateA;
            });

            $total = count($auditRows);
            $offset = ($page - 1) * $perPage;
            $paginatedRows = array_slice($auditRows, $offset, $perPage);

            $paginator = new LengthAwarePaginator(
                $paginatedRows,
                $total,
                $perPage,
                $page,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return ResponseHelper::success(
                [
                    'record' => [
                        'id' => $processRecord->id,
                        'process' => $processRecord->process?->name,
                        'stage' => $processRecord->stage?->name,
                        'department' => $processRecord->department?->name,
                        'initiator' => $processRecord->initiator?->name,
                        'initiation_date' => self::formatDate(
                            $processRecord->initiation_date,
                            'd-M-Y H:i:s'
                        ),
                    ],
                    'audits' => $paginator->items(),
                    'pagination' => [
                        'current_page' => $paginator->currentPage(),
                        'per_page' => $paginator->perPage(),
                        'total' => $paginator->total(),
                        'last_page' => $paginator->lastPage(),
                        'from' => $paginator->firstItem(),
                        'to' => $paginator->lastItem(),
                        'has_more_pages' => $paginator->hasMorePages(),
                    ],
                    'filters' => [
                        'search' => $search !== '' ? $search : null,
                        'from_date' => $fromDate ?: null,
                        'to_date' => $toDate ?: null,
                    ],
                ],
                'Process record audits fetched successfully.'
            );
        } catch (\Exception $e) {
            \Log::error('AUDIT HISTORY ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error(
                $e->getMessage() . ' | Line: ' . $e->getLine(),
                500
            );
        }
    }

    /* prepare process audit */
    private static function prepareProcessAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $oldData = self::getProcessDataValues($oldValue['process_data'] ?? []);
        $newData = self::getProcessDataValues($newValue['process_data'] ?? []);

        $rows = [];

        $fields = array_unique(array_merge(array_keys($oldData), array_keys($newData)));

        foreach ($fields as $label) {
            $old = $oldData[$label] ?? null;
            $new = $newData[$label] ?? null;

            if (self::valuesAreSame($old, $new)) {
                continue;
            }

            if (self::isEmptyValue($old) && self::isEmptyValue($new)) {
                continue;
            }

            $rows[] = self::makeAuditRow($audit, $label, $old, $new);
        }

        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) {
                continue;
            }

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (self::valuesAreSame($old, $new)) {
                continue;
            }

            if (self::isEmptyValue($old) && self::isEmptyValue($new)) {
                continue;
            }

            $rows[] = self::makeAuditRow(
                $audit,
                FieldLabelHelper::getLabel('Process Record', $field),
                $old,
                $new
            );
        }

        return $rows;
    }

    /* flatten process_data into label => value pairs */
    private static function getProcessDataValues($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $item) {
            if (is_array($item)) {
                if (array_key_exists('label', $item) && array_key_exists('value', $item)) {
                    $label = $item['label'];

                    if ($label) {
                        $result[$label] = $item['value'] ?? null;
                    }

                    continue;
                }

                if (isset($item['key']) && array_key_exists('value', $item)) {
                    $result[$item['key']] = $item['value'] ?? null;
                    continue;
                }
            }

            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /* render a value for display */
    private static function auditDisplayValue($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray')
                ? $value->toArray()
                : (array) $value;
        }

        if (!is_array($value)) {
            return (string) $value;
        }

        if (isset($value['name'])) {
            return (string) $value['name'];
        }

        if (isset($value['value']) && count($value) <= 3) {
            return self::auditDisplayValue($value['value']);
        }

        $lines = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) {
                continue;
            }

            if (is_array($item)) {
                $nestedLines = [];

                foreach ($item as $nestedKey => $nestedValue) {
                    if (self::isEmptyValue($nestedValue)) {
                        continue;
                    }

                    if (is_array($nestedValue)) {
                        $nestedValue = self::auditDisplayValue($nestedValue);
                    }

                    $nestedLines[] = self::formatFieldLabel((string) $nestedKey)
                        . ' : '
                        . $nestedValue;
                }

                if (!empty($nestedLines)) {
                    $lines[] = $key . ' : ' . implode(', ', $nestedLines);
                }

                continue;
            }

            $lines[] = self::formatFieldLabel((string) $key)
                . ' : '
                . $item;
        }

        return empty($lines)
            ? '-'
            : implode("\n", $lines);
    }

    /* grid record audit - one row per changed grid row */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldRows = self::extractGridRows($oldValue);
        $newRows = self::extractGridRows($newValue);

        $oldGrid = self::normalizeGridRows($oldRows);
        $newGrid = self::normalizeGridRows($newRows);

        $rowNumbers = array_unique(array_merge(array_keys($oldGrid), array_keys($newGrid)));
        sort($rowNumbers, SORT_NUMERIC);

        $rows = [];

        foreach ($rowNumbers as $rowNumber) {
            $oldRow = $oldGrid[$rowNumber] ?? null;
            $newRow = $newGrid[$rowNumber] ?? null;

            $oldFields = $oldRow['fields'] ?? [];
            $newFields = $newRow['fields'] ?? [];

            /* row deleted */
            if ($oldRow !== null && $newRow === null) {
                $oldDisplay = self::formatGridFieldsForAudit($oldFields);

                if ($oldDisplay === null) {
                    continue;
                }

                $rows[] = self::makeAuditRow($audit, 'Row ' . $rowNumber, $oldDisplay, null);

                continue;
            }

            /* row added */
            if ($oldRow === null && $newRow !== null) {
                $newDisplay = self::formatGridFieldsForAudit($newFields);

                if ($newDisplay === null) {
                    continue;
                }

                $rows[] = self::makeAuditRow($audit, 'Row ' . $rowNumber, null, $newDisplay);

                continue;
            }

            /* existing row updated - collect all changed fields into one row */
            $fieldKeys = array_unique(array_merge(array_keys($oldFields), array_keys($newFields)));

            $changedOldFields = [];
            $changedNewFields = [];

            foreach ($fieldKeys as $fieldKey) {
                $oldField = $oldFields[$fieldKey] ?? null;
                $newField = $newFields[$fieldKey] ?? null;

                $oldFieldValue = $oldField['value'] ?? null;
                $newFieldValue = $newField['value'] ?? null;

                if (self::isEmptyValue($oldFieldValue) && self::isEmptyValue($newFieldValue)) {
                    continue;
                }

                if (self::valuesAreSame($oldFieldValue, $newFieldValue)) {
                    continue;
                }

                $label = $newField['label'] ?? $oldField['label'] ?? self::formatFieldLabel($fieldKey);

                if (!self::isEmptyValue($oldFieldValue)) {
                    $changedOldFields[] = ['label' => $label, 'value' => $oldFieldValue];
                }

                if (!self::isEmptyValue($newFieldValue)) {
                    $changedNewFields[] = ['label' => $label, 'value' => $newFieldValue];
                }
            }

            if (empty($changedOldFields) && empty($changedNewFields)) {
                continue;
            }

            $rows[] = self::makeAuditRow(
                $audit,
                'Row ' . $rowNumber,
                self::formatGridFieldListForAudit($changedOldFields),
                self::formatGridFieldListForAudit($changedNewFields)
            );
        }

        return $rows;
    }

    /* checklist record audit */
    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRows = self::extractChecklistRows($oldValue) ?: [[]];
        $newRows = self::extractChecklistRows($newValue) ?: [[]];

        $oldRow = $oldRows[0];
        $newRow = $newRows[0];

        $keys = array_unique(array_merge(array_keys($oldRow), array_keys($newRow)));

        $rows = [];

        foreach ($keys as $key) {
            if (in_array(strtolower($key), ['id', 'row_id', 'checklist_record_id', 'process_record_id', 'created_at', 'updated_at'])) {
                continue;
            }

            $old = $oldRow[$key] ?? null;
            $new = $newRow[$key] ?? null;

            if (self::isEmptyValue($old) && self::isEmptyValue($new)) {
                continue;
            }

            if (self::valuesAreSame($old, $new)) {
                continue;
            }

            $rows[] = self::makeAuditRow($audit, self::formatFieldLabel($key), $old, $new);
        }

        return $rows;
    }

    /* activity audit */
    private static function prepareActivityAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $activityName = $newValue['activity']
            ?? $oldValue['activity']
            ?? $audit->module
            ?? 'Activity';

        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;

        $comment = $newValue['comment'] ?? null;

        if (self::isEmptyValue($comment)) {
            $comment = $audit->description ?? null;
        }

        if (
            self::isEmptyValue($oldStage) &&
            self::isEmptyValue($newStage) &&
            self::isEmptyValue($comment)
        ) {
            return null;
        }

        return [
            'id' => $audit->id,
            'action' => 'Activity Performed',

            'module' => $activityName,

            'old_value' => self::isEmptyValue($oldStage)
                ? null
                : self::formatDisplayValue($oldStage),

            'new_value' => self::isEmptyValue($newStage)
                ? null
                : self::formatDisplayValue($newStage),

            'comment' => self::isEmptyValue($comment)
                ? null
                : self::formatDisplayValue($comment),

            'responsible_person' => $audit->user?->name ?? '-',

            'user_id' => $audit->user_id,

            'record_id' => $audit->record_id,

            'model' => $audit->model,

            'created_at' => self::formatDate(
                $audit->created_at,
                'd-m-Y H:i:s'
            ),
        ];
    }

    /* build a single audit row */
    private static function makeAuditRow($audit, $module, $oldValue, $newValue)
    {
        return [
            'id' => $audit->id,
            'action' => $audit->action,
            'module' => $module,
            'old_value' => self::isEmptyValue($oldValue) ? null : self::formatDisplayValue($oldValue),
            'new_value' => self::isEmptyValue($newValue) ? null : self::formatDisplayValue($newValue),
            'comment' => null,
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatDate($audit->created_at, 'd-m-Y H:i:s'),
        ];
    }

    /* extract grid rows from an audit value */
    private static function extractGridRows($value)
    {
        if (!is_array($value)) {
            return [];
        }

        if (isset($value['grid_data']) && is_array($value['grid_data'])) {
            return array_values($value['grid_data']);
        }

        if (isset($value[0]) && is_array($value[0])) {
            return array_values($value);
        }

        return [];
    }

    /* normalize grid rows into row_number => fields structure */
    private static function normalizeGridRows($rows)
    {
        $result = [];

        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowNumber = $index + 1;
            $fields = [];

            foreach ($row as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                /* row number */
                if ($key === 'row') {
                    if ($value !== null && $value !== '') {
                        $rowNumber = (int) $value;
                    }

                    continue;
                }

                /* fields container - never exposed directly in audit */
                if ($key === 'fields') {
                    if (!is_array($value)) {
                        continue;
                    }

                    foreach ($value as $field) {
                        if (!is_array($field)) {
                            continue;
                        }

                        $fieldKey = $field['key'] ?? null;

                        if (!$fieldKey) {
                            continue;
                        }

                        $fields[$fieldKey] = [
                            'label' => $field['label'] ?? self::formatFieldLabel($fieldKey),
                            'value' => $field['value'] ?? null,
                        ];
                    }

                    continue;
                }

                /* fallback for a flat grid structure */
                if ($key) {
                    $fields[$key] = [
                        'label' => $item['label'] ?? self::formatFieldLabel($key),
                        'value' => $value,
                    ];
                }
            }

            $result[$rowNumber] = [
                'row_number' => $rowNumber,
                'fields' => $fields,
            ];
        }

        return $result;
    }

    /* format all non-empty fields of a grid row for audit */
    private static function formatGridFieldsForAudit($fields)
    {
        if (!is_array($fields)) {
            return null;
        }

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;

            if (self::isEmptyValue($value) || self::isEmptyValue($label)) {
                continue;
            }

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* format only the changed fields of a grid row for audit */
    private static function formatGridFieldListForAudit($fields)
    {
        if (!is_array($fields) || empty($fields)) {
            return null;
        }

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) {
                continue;
            }

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;

            if (self::isEmptyValue($label) || self::isEmptyValue($value)) {
                continue;
            }

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* extract checklist rows from an audit value */
    private static function extractChecklistRows($value)
    {
        if (!is_array($value)) {
            return [];
        }

        if (isset($value['checklist_data']) && is_array($value['checklist_data'])) {
            return array_values($value['checklist_data']);
        }

        if (isset($value[0]) && is_array($value[0])) {
            return array_values($value);
        }

        return [];
    }

    /* decode + clean a raw audit value */
    private static function prepareValue($value)
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        if (is_object($value)) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : $value;
        }

        return self::removeEmptyValues($value);
    }

    /* recursively strip empty values */
    private static function removeEmptyValues($value)
    {
        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) {
                continue;
            }

            if (is_string($item)) {
                $decoded = json_decode($item, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $item = $decoded;
                }
            }

            if (is_array($item)) {
                $cleaned = self::removeEmptyValues($item);

                if (!empty($cleaned)) {
                    $result[$key] = $cleaned;
                }

                continue;
            }

            if (!self::isEmptyValue($item)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /* check empty value */
    private static function isEmptyValue($value)
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            return empty($value);
        }

        return false;
    }

    /* compare two audit values for equality */
    private static function valuesAreSame($old, $new)
    {
        if (self::isEmptyValue($old) && self::isEmptyValue($new)) {
            return true;
        }

        if (is_array($old) || is_array($new)) {
            if (!is_array($old) || !is_array($new)) {
                return false;
            }

            return $old == $new;
        }

        if (is_object($old) || is_object($new)) {
            $old = is_object($old) ? $old->toArray() : $old;
            $new = is_object($new) ? $new->toArray() : $new;

            return self::valuesAreSame($old, $new);
        }

        return $old === $new;
    }

    /* format a value for the final audit row display */
    private static function formatDisplayValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value[0]) && is_array($value[0]) && array_key_exists('label', $value[0]) && array_key_exists('value', $value[0])) {
                return self::formatGridFieldListForAudit($value);
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /* format a raw key into a readable label */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    /* format a date value */
    private static function formatDate($date, $format = 'd-m-Y H:i:s')
    {
        if ($date === null || $date === '') {
            return null;
        }

        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    /* equipment master audit */
    public static function getEquipmentMasterAudit($recordId)
    {
        try {
            $audits = Audit::with('user')
                ->where('record_id', $recordId)
                ->where('model', 'App\Models\EquipmentMaster')
                ->orderBy('id', 'desc')
                ->get();

            $audits->transform(function ($audit) {
                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user?->name,
                    'module' => $audit->module,
                    'model' => $audit->model,
                    'action' => $audit->action,
                    'description' => $audit->description,
                    'record_id' => $audit->record_id,
                    'old_value' => self::prepareValue($audit->old_value),
                    'new_value' => self::prepareValue($audit->new_value),
                    'created_at' => self::formatDate($audit->created_at, 'd-m-Y H:i:s'),
                ];
            });

            return ResponseHelper::success($audits, 'Equipment Master audits fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Failed to retrieve equipment master audits.', 500);
        }
    }
}