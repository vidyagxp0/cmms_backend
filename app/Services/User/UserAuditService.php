<?php

namespace App\Services\User;

use App\Helpers\FieldLabelHelper;
use App\Helpers\UserAuditHelper;
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
    /* get paginated, filtered audit trail for a process record (process + grid + checklist + activity) */
    public static function getProcessRecordAudits($id, Request $request)
    {
        try {
            $perPage = 10;
            $page = max((int) $request->input('page', 1), 1);
            $search = trim((string) $request->input('search', ''));
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            $processRecord = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->findOrFail($id);

            /* child record ids used to pull grid/checklist audits */
            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id');
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)->pluck('id');

            $processAuditQuery = Audit::with('user')->where('record_id', $id)->where('model', ProcessRecord::class);
            $gridAuditQuery = Audit::with('user')->where('model', GridRecord::class)->whereIn('record_id', $gridRecordIds);
            $checklistAuditQuery = Audit::with('user')->where('model', ChecklistRecord::class)->whereIn('record_id', $checklistRecordIds);

            /* apply shared date range filter to all three queries */
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

            /* merge and sort all audits newest first */
            $audits = $processAuditQuery->get()
                ->concat($gridAuditQuery->get())
                ->concat($checklistAuditQuery->get())
                ->sortByDesc(fn($audit) => $audit->created_at ? Carbon::parse($audit->created_at)->timestamp : 0)
                ->values();

            $auditRows = [];

            /* convert each raw audit into one or more display rows, by type */
            foreach ($audits as $audit) {
                $oldValue = self::prepareValue($audit->old_value);
                $newValue = self::prepareValue($audit->new_value);

                if (strtolower(trim($audit->action)) === 'activity performed') {
                    $activityRow = self::prepareActivityAudit($audit, $oldValue, $newValue);
                    if ($activityRow) $auditRows[] = $activityRow;
                    continue;
                }

                if ($audit->model === ProcessRecord::class) {
                    foreach (self::prepareProcessAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }

                if ($audit->model === GridRecord::class) {
                    foreach (self::prepareGridAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }

                if ($audit->model === ChecklistRecord::class) {
                    foreach (self::prepareChecklistAudit($audit, $oldValue, $newValue) as $row) $auditRows[] = $row;
                    continue;
                }
            }

            /* free-text search across module/user/field */
            if ($search !== '') {
                $searchLower = strtolower($search);
                $auditRows = array_values(array_filter($auditRows, function ($row) use ($searchLower) {
                    return str_contains(strtolower((string) ($row['module'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['responsible_person'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['field'] ?? '')), $searchLower);
                }));
            }

            usort($auditRows, function ($a, $b) {
                $dateA = !empty($a['created_at']) ? Carbon::parse($a['created_at'])->timestamp : 0;
                $dateB = !empty($b['created_at']) ? Carbon::parse($b['created_at'])->timestamp : 0;
                return $dateB <=> $dateA;
            });

            /* manual pagination over the flattened row list */
            $total = count($auditRows);
            $offset = ($page - 1) * $perPage;
            $paginatedRows = array_slice($auditRows, $offset, $perPage);

            $paginator = new LengthAwarePaginator($paginatedRows, $total, $perPage, $page, [
                'path' => $request->url(),
                'query' => $request->query(),
            ]);

            return ResponseHelper::success([
                'record' => [
                    'id' => $processRecord->id,
                    'process' => $processRecord->process?->name,
                    'stage' => $processRecord->stage?->name,
                    'department' => $processRecord->department?->name,
                    'initiator' => $processRecord->initiator?->name,
                    'initiation_date' => self::formatDate($processRecord->initiation_date, 'd/m/Y H:i:s'),
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
            ], 'Process record audits fetched successfully.');
        } catch (\Exception $e) {
            \Log::error('AUDIT HISTORY ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error($e->getMessage() . ' | Line: ' . $e->getLine(), 500);
        }
    }

    /* build audit rows for a process-record change (process_data fields + top-level scalar fields) */
    private static function prepareProcessAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $oldData = self::getProcessDataValues($oldValue['process_data'] ?? []);
        $newData = self::getProcessDataValues($newValue['process_data'] ?? []);

        $rows = [];

        /* diff dynamic process_data fields */
        foreach (array_unique(array_merge(array_keys($oldData), array_keys($newData))) as $label) {
            $old = $oldData[$label] ?? null;
            $new = $newData[$label] ?? null;

            if (self::valuesAreSame($old, $new)) continue;
            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;

            $rows[] = self::makeAuditRow($audit, $label, $old, $new);
        }

        /* diff remaining top-level fields (skip ones already covered above) */
        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'])) continue;

            $old = $oldValue[$field] ?? null;
            $new = $newValue[$field] ?? null;

            if (self::valuesAreSame($old, $new)) continue;
            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;

            $rows[] = self::makeAuditRow($audit, FieldLabelHelper::getLabel('Process Record', $field), $old, $new);
        }

        return $rows;
    }

    /* flatten process_data into label => value pairs */
    private static function getProcessDataValues($data)
    {
        if (!is_array($data)) return [];

        $result = [];

        foreach ($data as $key => $item) {
            if (is_array($item)) {
                if (array_key_exists('label', $item) && array_key_exists('value', $item)) {
                    if ($item['label']) $result[$item['label']] = $item['value'] ?? null;
                    continue;
                }
                if (isset($item['key']) && array_key_exists('value', $item)) {
                    $result[$item['key']] = $item['value'] ?? null;
                    continue;
                }
            }

            if (is_string($key)) $result[$key] = $item;
        }

        return $result;
    }

    /* render a value (scalar, name-object, or nested array) for display */
    private static function auditDisplayValue($value)
    {
        if ($value === null || $value === '') return '-';

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) return (string) $value;
        if (isset($value['name'])) return (string) $value['name'];
        if (isset($value['value']) && count($value) <= 3) return self::auditDisplayValue($value['value']);

        $lines = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) continue;

            if (is_array($item)) {
                $nestedLines = [];

                foreach ($item as $nestedKey => $nestedValue) {
                    if (self::isEmptyValue($nestedValue)) continue;
                    if (is_array($nestedValue)) $nestedValue = self::auditDisplayValue($nestedValue);

                    $nestedLines[] = self::formatFieldLabel((string) $nestedKey) . ' : ' . $nestedValue;
                }

                if (!empty($nestedLines)) $lines[] = $key . ' : ' . implode(', ', $nestedLines);
                continue;
            }

            $lines[] = self::formatFieldLabel((string) $key) . ' : ' . $item;
        }

        return empty($lines) ? '-' : implode("\n", $lines);
    }

    /* grid record audit - one row per created/updated/deleted grid row */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldGrid = self::normalizeGridRows(self::extractGridRows($oldValue));
        $newGrid = self::normalizeGridRows(self::extractGridRows($newValue));

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
                if ($oldDisplay === null) continue;

                $rows[] = self::makeAuditRow($audit, 'Row ' . $rowNumber, $oldDisplay, null, 'Deleted');
                continue;
            }

            /* row added */
            if ($oldRow === null && $newRow !== null) {
                $newDisplay = self::formatGridFieldsForAudit($newFields);
                if ($newDisplay === null) continue;

                $rows[] = self::makeAuditRow($audit, 'Row ' . $rowNumber, null, $newDisplay, 'Created');
                continue;
            }

            /* existing row - collect all changed fields into a single row */
            $changedOldFields = [];
            $changedNewFields = [];

            foreach (array_unique(array_merge(array_keys($oldFields), array_keys($newFields))) as $fieldKey) {
                $oldField = $oldFields[$fieldKey] ?? null;
                $newField = $newFields[$fieldKey] ?? null;
                $oldFieldValue = $oldField['value'] ?? null;
                $newFieldValue = $newField['value'] ?? null;

                if (self::isEmptyValue($oldFieldValue) && self::isEmptyValue($newFieldValue)) continue;
                if (self::valuesAreSame($oldFieldValue, $newFieldValue)) continue;

                $label = $newField['label'] ?? $oldField['label'] ?? self::formatFieldLabel($fieldKey);

                if (!self::isEmptyValue($oldFieldValue)) $changedOldFields[] = ['label' => $label, 'value' => $oldFieldValue];
                if (!self::isEmptyValue($newFieldValue)) $changedNewFields[] = ['label' => $label, 'value' => $newFieldValue];
            }

            if (empty($changedOldFields) && empty($changedNewFields)) continue;

            $rows[] = self::makeAuditRow(
                $audit,
                'Row ' . $rowNumber,
                self::formatGridFieldListForAudit($changedOldFields),
                self::formatGridFieldListForAudit($changedNewFields),
                'Updated'
            );
        }

        return $rows;
    }

    /* checklist record audit - single row per changed field */
    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRow = (self::extractChecklistRows($oldValue) ?: [[]])[0];
        $newRow = (self::extractChecklistRows($newValue) ?: [[]])[0];

        $rows = [];
        $skip = ['id', 'row_id', 'checklist_record_id', 'process_record_id', 'created_at', 'updated_at'];

        foreach (array_unique(array_merge(array_keys($oldRow), array_keys($newRow))) as $key) {
            if (in_array(strtolower($key), $skip)) continue;

            $old = $oldRow[$key] ?? null;
            $new = $newRow[$key] ?? null;

            if (self::isEmptyValue($old) && self::isEmptyValue($new)) continue;
            if (self::valuesAreSame($old, $new)) continue;

            $rows[] = self::makeAuditRow($audit, self::formatFieldLabel($key), $old, $new);
        }

        return $rows;
    }

    /* activity (stage move) audit */
    private static function prepareActivityAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $activityName = $newValue['activity'] ?? $oldValue['activity'] ?? $audit->module ?? 'Activity';
        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;
        $comment = $newValue['comment'] ?? null;

        if (self::isEmptyValue($comment)) $comment = $audit->description ?? null;

        if (self::isEmptyValue($oldStage) && self::isEmptyValue($newStage) && self::isEmptyValue($comment)) {
            return null;
        }

        return [
            'id' => $audit->id,
            'action' => 'Activity Performed',
            'module' => $activityName,
            'old_value' => self::isEmptyValue($oldStage) ? null : self::formatDisplayValue($oldStage),
            'new_value' => self::isEmptyValue($newStage) ? null : self::formatDisplayValue($newStage),
            'comment' => self::isEmptyValue($comment) ? null : self::formatDisplayValue($comment),
            'responsible_person' => $audit->user?->name ?? '-',
            'user_id' => $audit->user_id,
            'record_id' => $audit->record_id,
            'model' => $audit->model,
            'created_at' => self::formatDate($audit->created_at, 'd/m/Y H:i:s'),
        ];
    }

    /* build a single display-ready audit row */
    private static function makeAuditRow($audit, $module, $oldValue, $newValue, $action = null)
    {
        return [
            'id' => $audit->id,
            'action' => $action ?? $audit->action,
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

    /* pull the raw grid row array out of an audit value */
    private static function extractGridRows($value)
    {
        if (!is_array($value)) return [];
        if (isset($value['grid_data']) && is_array($value['grid_data'])) return array_values($value['grid_data']);
        if (isset($value[0]) && is_array($value[0])) return array_values($value);

        return [];
    }

    /* normalize grid rows into row_number => fields structure, regenerating labels from keys for consistency */
    private static function normalizeGridRows($rows)
    {
        $result = [];
        if (!is_array($rows)) return $result;

        foreach ($rows as $index => $row) {
            if (!is_array($row)) continue;

            $rowNumber = $index + 1;
            $fields = [];

            foreach ($row as $item) {
                if (!is_array($item)) continue;

                $key = $item['key'] ?? null;
                $value = $item['value'] ?? null;

                /* row number override */
                if ($key === 'row') {
                    if ($value !== null && $value !== '') $rowNumber = (int) $value;
                    continue;
                }

                /* nested fields container */
                if ($key === 'fields') {
                    if (!is_array($value)) continue;

                    foreach ($value as $field) {
                        if (!is_array($field)) continue;

                        $fieldKey = $field['key'] ?? $field['Key'] ?? null;
                        if (!$fieldKey) continue;

                        $fieldValue = array_key_exists('value', $field) ? $field['value'] : ($field['Value'] ?? null);
                        $fields[$fieldKey] = ['label' => UserAuditHelper::getGridFieldLabel($fieldKey), 'value' => $fieldValue];
                    }
                    continue;
                }

                /* fallback for flat grid structure */
                if ($key) {
                    $fields[$key] = ['label' => UserAuditHelper::getGridFieldLabel($key), 'value' => $value];
                }
            }

            $result[$rowNumber] = ['row_number' => $rowNumber, 'fields' => $fields];
        }

        return $result;
    }

    /* format every non-empty field of a grid row */
    private static function formatGridFieldsForAudit($fields)
    {
        if (!is_array($fields)) return null;

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;
            if (self::isEmptyValue($value) || self::isEmptyValue($label)) continue;

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* format only the changed fields of a grid row */
    private static function formatGridFieldListForAudit($fields)
    {
        if (!is_array($fields) || empty($fields)) return null;

        $lines = [];

        foreach ($fields as $field) {
            if (!is_array($field)) continue;

            $label = $field['label'] ?? null;
            $value = $field['value'] ?? null;
            if (self::isEmptyValue($label) || self::isEmptyValue($value)) continue;

            $lines[] = $label . ' : ' . self::auditDisplayValue($value);
        }

        return empty($lines) ? null : implode("\n", $lines);
    }

    /* pull the raw checklist row array out of an audit value */
    private static function extractChecklistRows($value)
    {
        if (!is_array($value)) return [];
        if (isset($value['checklist_data']) && is_array($value['checklist_data'])) return array_values($value['checklist_data']);
        if (isset($value[0]) && is_array($value[0])) return array_values($value);

        return [];
    }

    /* decode (if JSON string) and strip empty values from a raw audit value */
    private static function prepareValue($value)
    {
        if ($value === null) return null;

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) $value = $decoded;
        }

        if (is_object($value)) $value = $value->toArray();
        if (!is_array($value)) return self::isEmptyValue($value) ? null : $value;

        return self::removeEmptyValues($value);
    }

    /* recursively strip empty values, decoding nested JSON strings along the way */
    private static function removeEmptyValues($value)
    {
        if (!is_array($value)) return self::isEmptyValue($value) ? null : $value;

        $result = [];

        foreach ($value as $key => $item) {
            if (self::isEmptyValue($item)) continue;

            if (is_string($item)) {
                $decoded = json_decode($item, true);
                if (json_last_error() === JSON_ERROR_NONE) $item = $decoded;
            }

            if (is_array($item)) {
                $cleaned = self::removeEmptyValues($item);
                if (!empty($cleaned)) $result[$key] = $cleaned;
                continue;
            }

            if (!self::isEmptyValue($item)) $result[$key] = $item;
        }

        return $result;
    }

    /* check for null / blank string / empty array */
    private static function isEmptyValue($value)
    {
        if ($value === null) return true;
        if (is_string($value)) return trim($value) === '';
        if (is_array($value)) return empty($value);

        return false;
    }

    /* compare two audit values for equality (arrays, objects, or scalars) */
    private static function valuesAreSame($old, $new)
    {
        if (self::isEmptyValue($old) && self::isEmptyValue($new)) return true;

        if (is_array($old) || is_array($new)) {
            if (!is_array($old) || !is_array($new)) return false;
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
        if ($value === null || $value === '') return null;
        if (is_string($value)) return $value;

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

    /* turn a raw key like "some_field" into "Some Field" */
    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    /* format a date value, tolerating Carbon instances, strings, or bad input */
    private static function formatDate($date, $format = 'd/m/Y H:i:s')
    {
        if ($date === null || $date === '') return null;
        if ($date instanceof \DateTimeInterface) return $date->format($format);

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            return (string) $date;
        }
    }

    /* equipment master audit trail */
    public static function getEquipmentMasterAudit($recordId)
    {
        try {
            $audits = Audit::with('user')
                ->where('record_id', $recordId)
                ->where('model', 'App\Models\EquipmentMaster')
                ->orderBy('id', 'desc')
                ->get();

            $audits->transform(fn($audit) => [
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
                'created_at' => self::formatDate($audit->created_at, 'd/m/Y H:i:s'),
            ]);

            return ResponseHelper::success($audits, 'Equipment Master audits fetched successfully.');
        } catch (\Exception $e) {
            return ResponseHelper::error('Failed to retrieve equipment master audits.', 500);
        }
    }
}