<?php

namespace App\Services\User;

use App\Helpers\ResponseHelper;
use App\Models\Audit;
use App\Models\GridRecord;
use App\Models\ChecklistRecord;

class UserAuditService
{
    /* get complete process record audit history */
    public static function getProcessRecordAudits($id)
    {
        try {
            /* get grid record ids belonging to main process record */
            $gridRecordIds = GridRecord::where('process_record_id', $id)
                ->pluck('id');

            /* get checklist record ids belonging to main process record */
            $checklistRecordIds = ChecklistRecord::where('process_record_id', $id)
                ->pluck('id');

            /* get process record audits */
            $processAudits = Audit::with('user')
                ->where('record_id', $id)
                ->where('model', 'App\Models\ProcessRecord')
                ->get();

            /* get grid record audits */
            $gridAudits = Audit::with('user')
                ->where('model', 'App\Models\GridRecord')
                ->whereIn('record_id', $gridRecordIds)
                ->get();

            /* get checklist record audits */
            $checklistAudits = Audit::with('user')
                ->where('model', 'App\Models\ChecklistRecord')
                ->whereIn('record_id', $checklistRecordIds)
                ->get();

            /* merge all audit records */
            $audits = $processAudits
                ->concat($gridAudits)
                ->concat($checklistAudits)
                ->sortByDesc(function ($audit) {
                    return $audit->created_at?->timestamp ?? 0;
                })
                ->values();

            /* format audit response */
            $audits->transform(function ($audit) {
                $oldValue = self::prepareValue($audit->old_value);
                $newValue = self::prepareValue($audit->new_value);

                /* flatten process data */
                if ($audit->model === 'App\Models\ProcessRecord') {
                    $oldValue = self::flattenProcessRecordData($oldValue);
                    $newValue = self::flattenProcessRecordData($newValue);
                }

                /* extract only grid data */
                if ($audit->model === 'App\Models\GridRecord') {
                    $oldValue = self::extractGridData($oldValue);
                    $newValue = self::extractGridData($newValue);
                }

                /* extract only checklist data */
                if ($audit->model === 'App\Models\ChecklistRecord') {
                    $oldValue = self::extractChecklistData($oldValue);
                    $newValue = self::extractChecklistData($newValue);
                }

                return [
                    'id' => $audit->id,
                    'user_id' => $audit->user_id,
                    'user_name' => $audit->user?->name,
                    'module' => $audit->module,
                    'model' => $audit->model,
                    'model_name' => self::getModelName($audit->model),
                    'action' => $audit->action,
                    'description' => $audit->description,
                    'record_id' => $audit->record_id,
                    'old_value' => $oldValue,
                    'new_value' => $newValue,
                    'created_at' => $audit->created_at
                        ? $audit->created_at->format('d-m-Y H:i:s')
                        : null,
                ];
            });

            return ResponseHelper::success(
                $audits,
                'Process record audits fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve process record audits.',
                500
            );
        }
    }

    /* flatten process record audit data */
    private static function flattenProcessRecordData($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = $value;

        /* move process_data fields to root level */
        if (isset($result['process_data']) && is_array($result['process_data'])) {
            $processData = $result['process_data'];

            unset($result['process_data']);

            foreach ($processData as $key => $item) {
                if (self::isEmptyValue($item)) {
                    continue;
                }

                $result[$key] = $item;
            }
        }

        return self::removeEmptyValues($result);
    }

    /* extract grid data */
    private static function extractGridData($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists('grid_data', $value)) {
            return self::removeEmptyValues($value['grid_data']);
        }

        return self::removeEmptyValues($value);
    }

    /* extract checklist data */
    private static function extractChecklistData($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_key_exists('checklist_data', $value)) {
            return self::removeEmptyValues($value['checklist_data']);
        }

        return self::removeEmptyValues($value);
    }

    /* prepare audit value */
    private static function prepareValue($value)
    {
        if ($value === null) {
            return null;
        }

        /* decode JSON string */
        if (is_string($value)) {
            $decoded = json_decode($value, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            }
        }

        /* convert object to array */
        if (is_object($value)) {
            $value = $value->toArray();
        }

        if (!is_array($value)) {
            return self::isEmptyValue($value) ? null : $value;
        }

        return self::removeEmptyValues($value);
    }

    /* recursively remove empty values */
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

        if ($value === '') {
            return true;
        }

        if (is_array($value) && empty($value)) {
            return true;
        }

        return false;
    }

    /* get readable model name */
    private static function getModelName($model)
    {
        return match ($model) {
            'App\Models\ProcessRecord' => 'Process Record',
            'App\Models\GridRecord' => 'Grid Record',
            'App\Models\ChecklistRecord' => 'Checklist Record',
            default => class_basename($model),
        };
    }

    /* get equipment master audits data */
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
                    'created_at' => $audit->created_at
                        ? $audit->created_at->format('d-m-Y H:i:s')
                        : null,
                ];
            });

            return ResponseHelper::success(
                $audits,
                'Equipment Master audits fetched successfully.'
            );
        } catch (\Exception $e) {
            return ResponseHelper::error(
                'Failed to retrieve equipment master audits.',
                500
            );
        }
    }
}