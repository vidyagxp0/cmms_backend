<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Models\Audit;
use App\Models\GridRecord;
use App\Models\ChecklistRecord;
use App\Models\ProcessRecord;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class UserAuditService
{
    /* process record audit */
    public static function getProcessRecordAudits($id, Request $request)
    {
        try {
            /* paginations */
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

            /* child reocrd Ids */
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

                /* for activities */
                if (strtolower(trim($audit->action)) === 'activity performed') {
                    $activityRow = self::prepareActivityAudit($audit, $oldValue, $newValue);

                    if ($activityRow) {
                        $auditRows[] = $activityRow;
                    }

                    continue;
                }

                if ($audit->model === ProcessRecord::class) {
                    $rows = self::prepareProcessAudit($audit, $oldValue, $newValue);

                    foreach ($rows as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }

                if ($audit->model === GridRecord::class) {
                    $rows = self::prepareGridAudit($audit, $oldValue, $newValue);

                    foreach ($rows as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }

                if ($audit->model === ChecklistRecord::class) {
                    $rows = self::prepareChecklistAudit($audit, $oldValue, $newValue);

                    foreach ($rows as $row) {
                        $auditRows[] = $row;
                    }

                    continue;
                }
            }

            if ($search !== '') {
                $searchLower = strtolower($search);

                $auditRows = array_values(
                    array_filter($auditRows, function ($row) use ($searchLower) {
                        $module = strtolower((string) ($row['module'] ?? ''));
                        $fieldName = strtolower((string) ($row['field_name'] ?? ''));
                        $responsiblePerson = strtolower((string) ($row['responsible_person'] ?? $row['user_name'] ?? ''));

                        return
                            str_contains($module, $searchLower)
                            || str_contains($fieldName, $searchLower)
                            || str_contains($responsiblePerson, $searchLower);
                    })
                );
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
                ],
                'Process record audits fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /* prepare record audit */
    private static function prepareProcessAudit($audit, $oldValue, $newValue)
    {
        $oldValue = self::flattenProcessRecordData($oldValue);
        $newValue = self::flattenProcessRecordData($newValue);

        $keys = array_unique(
            array_merge(
                array_keys(is_array($oldValue) ? $oldValue : []),
                array_keys(is_array($newValue) ? $newValue : [])
            )
        );

        $rows = [];

        foreach ($keys as $key) {
            /* ignore technical fields */
            if (in_array(strtolower($key), ['id', 'process_record_id', 'created_at', 'updated_at'])) {
                continue;
            }

            $old = is_array($oldValue) ? ($oldValue[$key] ?? null) : null;
            $new = is_array($newValue) ? ($newValue[$key] ?? null) : null;

            /* ignore empty/NULL values */
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

    /* grid record audit */
    private static function prepareGridAudit($audit, $oldValue, $newValue)
    {
        $oldRows = self::extractGridRows($oldValue);
        $newRows = self::extractGridRows($newValue);

        if (empty($oldRows) && empty($newRows)) {
            return [];
        }

        $allRows = [];

        foreach ($oldRows as $row) {
            $rowId = $row['row_id'] ?? $row['id'] ?? null;

            if ($rowId !== null) {
                $allRows[$rowId]['old'] = $row;
            }
        }

        foreach ($newRows as $row) {
            $rowId = $row['row_id'] ?? $row['id'] ?? null;

            if ($rowId !== null) {
                $allRows[$rowId]['new'] = $row;
            }
        }

        if (empty($allRows)) {
            $allRows[1] = [
                'old' => $oldRows[0] ?? [],
                'new' => $newRows[0] ?? [],
            ];
        }

        $rows = [];

        foreach ($allRows as $rowData) {
            $oldRow = $rowData['old'] ?? [];
            $newRow = $rowData['new'] ?? [];

            $keys = array_unique(array_merge(array_keys($oldRow), array_keys($newRow)));

            foreach ($keys as $key) {
                if (in_array(strtolower($key), ['id', 'row_id', 'grid_record_id', 'process_record_id', 'created_at', 'updated_at'])) {
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

                $fieldLabel = self::formatFieldLabel($key);

                $rowNumber = self::getRowNumber($allRows, $rowData);

                if ($rowNumber !== null) {
                    $fieldLabel .= ' (Row ' . $rowNumber . ')';
                }

                $rows[] = self::makeAuditRow($audit, $fieldLabel, $old, $new);
            }
        }

        return $rows;
    }

    /* checklist record audit */
    private static function prepareChecklistAudit($audit, $oldValue, $newValue)
    {
        $oldRows = self::extractChecklistRows($oldValue);
        $newRows = self::extractChecklistRows($newValue);

        $oldRows = $oldRows ?: [[]];
        $newRows = $newRows ?: [[]];

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

        $activityName = $newValue['activity'] ?? $audit->module ?? 'Activity';

        $oldStage = $oldValue['stage'] ?? null;
        $newStage = $newValue['stage'] ?? null;

        $comment = $newValue['comment'] ?? null;

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

    private static function flattenProcessRecordData($value)
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            // Unnest process_data
            if ($key === 'process_data' && is_array($item)) {
                foreach ($item as $processKey => $processValue) {
                    if (!self::isEmptyValue($processValue)) {
                        $result[$processKey] = $processValue;
                    }
                }

                continue;
            }

            if (!self::isEmptyValue($item)) {
                $result[$key] = $item;
            }
        }

        return self::removeEmptyValues($result);
    }

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

    /* remove empty values */
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

    private static function valuesAreSame($old, $new)
    {
        if (self::isEmptyValue($old) && self::isEmptyValue($new)) {
            return true;
        }

        if (is_array($old) && is_array($new)) {
            return $old == $new;
        }

        return (string) $old === (string) $new;
    }

    private static function formatDisplayValue($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }

    private static function formatFieldLabel($key)
    {
        $key = str_replace(['_', '-'], ' ', $key);
        $key = str_replace('/', ' / ', $key);

        return ucwords(strtolower(trim($key)));
    }

    private static function getRowNumber($allRows, $currentRow)
    {
        $index = 1;

        foreach ($allRows as $rowData) {
            if ($rowData === $currentRow) {
                return $index;
            }

            $index++;
        }

        return null;
    }

    private static function formatDate($date, $format = 'd-m-Y H:i:s')
    {
        if ($date === null || $date === '') {
            return null;
        }

        // Already a DateTime/Carbon object
        if ($date instanceof \DateTimeInterface) {
            return $date->format($format);
        }

        try {
            return Carbon::parse($date)->format($format);
        } catch (\Exception $e) {
            // Parsing failed, return original
            return (string) $date;
        }
    }

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