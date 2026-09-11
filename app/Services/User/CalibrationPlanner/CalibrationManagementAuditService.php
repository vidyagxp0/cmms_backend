<?php

namespace App\Services\User\CalibrationPlanner;

use App\Helpers\FieldLabelHelper;
use App\Helpers\ResponseHelper;
use App\Models\Audit;
use App\Models\GridRecord;
use App\Models\ProcessRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class CalibrationManagementAuditService
{
    /* get calibration management audits */
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

            $processRecord = ProcessRecord::with(['process', 'stage', 'department', 'initiator'])->findOrFail($id);

            /* process record audits */
            $processAuditQuery = Audit::with('user')
                ->where('record_id', $id)
                ->where('model', ProcessRecord::class);

            /* grid record audits */
            $gridRecordIds = GridRecord::where('process_record_id', $id)->pluck('id');

            $gridAuditQuery = Audit::with('user')
                ->where('model', GridRecord::class)
                ->whereIn('record_id', $gridRecordIds);

            /* date filters */
            if (!empty($fromDate)) {
                $from = Carbon::parse($fromDate)->startOfDay();
                $processAuditQuery->where('created_at', '>=', $from);
                $gridAuditQuery->where('created_at', '>=', $from);
            }

            if (!empty($toDate)) {
                $to = Carbon::parse($toDate)->endOfDay();
                $processAuditQuery->where('created_at', '<=', $to);
                $gridAuditQuery->where('created_at', '<=', $to);
            }

            $processAudits = $processAuditQuery->get();
            $gridAudits = $gridAuditQuery->get();

            /* merge audits */
            $audits = $processAudits
                ->concat($gridAudits)
                ->sortByDesc(function ($audit) {
                    return $audit->created_at ? Carbon::parse($audit->created_at)->timestamp : 0;
                })
                ->values();

            /* prepare audit rows */
            $auditRows = [];

            foreach ($audits as $audit) {
                $oldValue = self::prepareValue($audit->old_value);
                $newValue = self::prepareValue($audit->new_value);

                /* activity audit */
                if (strtolower(trim((string) $audit->action)) === 'activity performed') {
                    $activityRow = self::prepareActivityAudit($audit, $oldValue, $newValue);

                    if ($activityRow) {
                        $auditRows[] = $activityRow;
                    }

                    continue;
                }

                /* process record audit */
                if ($audit->model === ProcessRecord::class) {
                    foreach (self::prepareProcessAudit($audit, $oldValue, $newValue) as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }

                /* grid record audit */
                if ($audit->model === GridRecord::class) {
                    foreach (self::prepareGridAudit($audit, $oldValue, $newValue) as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }
            }

            /* search */
            if ($search !== '') {
                $searchLower = strtolower($search);

                $auditRows = array_values(array_filter($auditRows, function ($row) use ($searchLower) {
                    return str_contains(strtolower((string) ($row['module'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['responsible_person'] ?? '')), $searchLower)
                        || str_contains(strtolower((string) ($row['field'] ?? '')), $searchLower);
                }));
            }

            /* sort */
            usort($auditRows, function ($a, $b) {
                $dateA = !empty($a['created_at']) ? Carbon::parse($a['created_at'])->timestamp : 0;
                $dateB = !empty($b['created_at']) ? Carbon::parse($b['created_at'])->timestamp : 0;

                return $dateB <=> $dateA;
            });

            /* pagination */
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
                    'initiation_date' => self::formatDate($processRecord->initiation_date, 'd-M-Y H:i:s'),
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
            ], 'Calibration Management audits fetched successfully.');

        } catch (\Exception $e) {
            \Log::error('CALIBRATION MANAGEMENT AUDIT ERROR', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return ResponseHelper::error($e->getMessage() . ' | Line: ' . $e->getLine(), 500);
        }
    }

    /* process record audit */
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

        /* process record top-level fields */
        foreach (array_unique(array_merge(array_keys($oldValue), array_keys($newValue))) as $field) {
            if (in_array($field, ['process_data', 'record_number', 'short_description'], true)) {
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

            $rows[] = self::makeAuditRow($audit, FieldLabelHelper::getLabel('Calibration Management', $field), $old, $new);
        }

        return $rows;
    }

    /* process data */
    private static function getProcessDataValues($data)
    {
        if (!is_array($data)) {
            return [];
        }

        $result = [];

        foreach ($data as $key => $item) {
            /* normal process field: ['key' => .., 'label' => .., 'value' => ..] */
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

            /* cleaned audit structure: "Label" => "value" */
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    /* grid audit */
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

            /* existing row updated */
            $fieldKeys = array_unique(array_merge(array_keys($oldFields), array_keys($newFields)));

            $changedOldFields = [];
            $changedNewFields = [];

            foreach ($fieldKeys as $fieldKey) {
                $oldField = $oldFields[$fieldKey] ?? null;
                $newField = $newFields[$fieldKey] ?? null;

                $oldFieldValue = is_array($oldField) ? ($oldField['value'] ?? null) : $oldField;
                $newFieldValue = is_array($newField) ? ($newField['value'] ?? null) : $newField;

                if (self::isEmptyValue($oldFieldValue) && self::isEmptyValue($newFieldValue)) {
                    continue;
                }

                if (self::valuesAreSame($oldFieldValue, $newFieldValue)) {
                    continue;
                }

                $label = null;

                if (is_array($newField)) {
                    $label = $newField['label'] ?? null;
                }

                if (!$label && is_array($oldField)) {
                    $label = $oldField['label'] ?? null;
                }

                $label = $label ?: self::formatFieldLabel($fieldKey);

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

    /* grid row extraction */
    private static function extractGridRows($value)
    {
        if (!is_array($value)) {
            return [];
        }

        /* standard audit structure */
        if (isset($value['grid_data']) && is_array($value['grid_data'])) {
            return array_values($value['grid_data']);
        }

        /* direct rows */
        if (isset($value[0]) && is_array($value[0])) {
            return array_values($value);
        }

        return [];
    }

    /* normalize grid rows */
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

            /* structure: [['key' => .., 'label' => .., 'value' => ..]] */
            foreach ($row as $key => $item) {
                if (is_array($item) && (isset($item['key']) || isset($item['label']))) {
                    $fieldKey = $item['key'] ?? $key;

                    if (in_array(strtolower((string) $fieldKey), ['row_id', 'id', 'grid_record_id', 'process_record_id'], true)) {
                        continue;
                    }

                    $fields[$fieldKey] = [
                        'label' => $item['label'] ?? self::formatFieldLabel($fieldKey),
                        'value' => $item['value'] ?? null,
                    ];

                    continue;
                }

                /* cleaned structure: 'reading1' => 'ok' */
                if (is_string($key) && !in_array(strtolower($key), ['row_id', 'id', 'grid_record_id', 'process_record_id'], true)) {
                    $fields[$key] = [
                        'label' => self::formatFieldLabel($key),
                        'value' => $item,
                    ];
                }
            }

            /* handle explicit row number */
            if (isset($row['row_id']) && is_numeric($row['row_id'])) {
                $rowNumber = (int) $row['row_id'];
            }

            $result[$rowNumber] = [
                'row_number' => $rowNumber,
                'fields' => $fields,
            ];
        }

        return $result;
    }

    /* grid display helpers */
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

    private static function auditDisplayValue($value)
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if (is_object($value)) {
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (is_array($value)) {
            if (isset($value['name'])) {
                return (string) $value['name'];
            }

            if (isset($value['value']) && count($value) <= 3) {
                return self::auditDisplayValue($value['value']);
            }

            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    /* activity audit */
    private static function prepareActivityAudit($audit, $oldValue, $newValue)
    {
        $oldValue = is_array($oldValue) ? $oldValue : [];
        $newValue = is_array($newValue) ? $newValue : [];

        $activityName = $newValue['activity'] ?? $oldValue['activity'] ?? $audit->module ?? 'Activity';

        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;

        $comment = $newValue['comment'] ?? null;

        if (self::isEmptyValue($comment)) {
            $comment = $audit->description ?? null;
        }

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
            'created_at' => self::formatDate($audit->created_at, 'd-m-Y H:i:s'),
        ];
    }

    /* common audit row */
    private static function makeAuditRow($audit, $field, $oldValue, $newValue)
    {
        return [
            'id' => $audit->id,
            'action' => $audit->action,
            'module' => $audit->module ?: 'Calibration Management',
            'field' => $field,
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

    /* value preparation */
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
            $value = method_exists($value, 'toArray') ? $value->toArray() : (array) $value;
        }

        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : $value;
        }

        return self::removeEmptyValues($value);
    }

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

    /* comparison */
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

    /* display */
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
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

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
}