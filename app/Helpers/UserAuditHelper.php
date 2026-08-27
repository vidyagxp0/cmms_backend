<?php

namespace App\Helpers;

use App\Models\Audit;
use App\Models\Department;
use App\Models\Process;
use App\Models\Stage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use App\Helpers\FieldLabelHelper;

class UserAuditHelper
{
    /* create user audit */
    public static function log(
        string $module,
        string $action,
        string $description,
        $recordId = null,
        $oldValue = null,
        $newValue = null,
        ?string $model = null
    ) {
        return Audit::create([
            'user_id' => Auth::id(),
            'module' => $module,
            'model' => $model,
            'action' => $action,
            'description' => $description,
            'record_id' => $recordId,
            'old_value' => self::prepareValue($oldValue, $module),
            'new_value' => self::prepareValue($newValue, $module),
        ]);
    }

    /* prepare audit data */
    private static function prepareValue($value, string $module)
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

        /** formate object data */
        if (is_object($value)) {
            $value = $value->toArray();
        }

        /** formate array data */
        if (!is_array($value)) {
            return $value;
        }
        return self::cleanArray($value, $module);
    }

    /* clean array data */
    private static function cleanArray(array $data, string $module)
    {
        $result = [];
        foreach ($data as $key => $value) {
            if (in_array($key, [
                'id',
                'created_at',
                'updated_at',
                'deleted_at',
            ])) {
                continue;
            }
            if ($key === 'department_id') {
                $result['Department'] = self::getDepartmentName($value);
                continue;
            }
            if ($key === 'initiator_id') {
                $result['Initiator'] = self::getUserName($value);
                continue;
            }
            if ($key === 'process_id') {
                $result['Process'] = self::getProcessName($value);
                continue;
            }
            if ($key === 'stage_id') {
                $result['Stage'] = self::getStageName($value);
                continue;
            }
            if (is_array($value)) {
                if (self::isProcessDataArray($value)) {
                    $processed = self::cleanProcessData($value);
                    if (!empty($processed)) {
                        $result['process_data'] = $processed;
                    }
                    continue;
                }

                $label = FieldLabelHelper::getLabel($module, $key);
                $cleaned = self::cleanNestedValue($value);
                if ($cleaned === [] || $cleaned === null) {
                    continue;
                }
                $result[$label] = $cleaned;
                continue;
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    if (self::isProcessDataArray($decoded)) {
                        $processed = self::cleanProcessData($decoded);
                        if (!empty($processed)) {
                            $result['process_data'] = $processed;
                        }
                        continue;
                    }

                    $label = FieldLabelHelper::getLabel($module, $key);
                    $cleaned = self::cleanNestedValue($decoded);
                    if ($cleaned === [] || $cleaned === null) {
                        continue;
                    }
                    $result[$label] = $cleaned;
                    continue;
                }
            }
            $label = FieldLabelHelper::getLabel($module, $key);
            $result[$label] = $value;
        }
        return $result;
    }

    /* Clean nested JSON / array values */
    private static function cleanNestedValue($value)
    {
        if (!is_array($value)) {
            if ($value === null || $value === '') {
                return null;
            }
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            if ($item === null || $item === '') {
                continue;
            }
            if (is_array($item)) {
                $cleaned = self::cleanNestedValue($item);
                if ($cleaned === null || $cleaned === []) {
                    continue;
                }
                $result[$key] = $cleaned;
                continue;
            }
            if (is_object($item)) {
                $cleaned = self::cleanNestedValue(
                    $item->toArray()
                );
                if ($cleaned === null || $cleaned === []) {
                    continue;
                }
                $result[$key] = $cleaned;
                continue;
            }
            if (is_string($item)) {
                $decoded = json_decode($item, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cleaned = self::cleanNestedValue($decoded);
                    if ($cleaned === null || $cleaned === []) {
                        continue;
                    }
                    $result[$key] = $cleaned;
                    continue;
                }
            }
            $result[$key] = $item;
        }
        return $result;
    }

    /* get department name */
    private static function getDepartmentName($id)
    {
        if (!$id) {
            return null;
        }
        return Department::where('id', $id)->value('name');
    }

    /* get user name */
    private static function getUserName($id)
    {
        if (!$id) {
            return null;
        }
        return User::where('id', $id)->value('name');
    }

    /* get process name */
    private static function getProcessName($id)
    {
        if (!$id) {
            return null;
        }
        return Process::where('id', $id)->value('name');
    }

    /* get stage name */
    private static function getStageName($id)
    {
        if (!$id) {
            return null;
        }
        return Stage::where('id', $id)->value('name');
    }

    /* remove null values and blank array storing in audits */
    private static function isProcessDataArray($value): bool
    {
        if (!is_array($value) || empty($value)) {
            return false;
        }
        foreach ($value as $item) {
            if (!is_array($item)) {
                return false;
            }
            if (
                !array_key_exists('key', $item) ||
                !array_key_exists('label', $item) ||
                !array_key_exists('value', $item)
            ) {
                return false;
            }
        }
        return true;
    }

    private static function cleanProcessData(array $data): array
    {
        $result = [];
        foreach ($data as $item) {
            if (!is_array($item)) {
                continue;
            }

            $label = $item['label'] ?? null;
            $value = $item['value'] ?? null;
            if (!$label) {
                continue;
            }
            if ($value === null || $value === '') {
                continue;
            }
            if (is_array($value)) {
                $value = self::cleanNestedValue($value);
                if (empty($value)) {
                    continue;
                }
            }
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $value = self::cleanNestedValue($decoded);
                    if (empty($value)) {
                        continue;
                    }
                }
            }
            $result[$label] = $value;
        }
        return $result;
    }
}