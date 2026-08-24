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

            /* remove default db fields */
            if (in_array($key, [
                'id',
                'created_at',
                'updated_at',
                'deleted_at',
            ])) {
                continue;
            }

            /* department */
            if ($key === 'department_id') {
                $result['Department'] = self::getDepartmentName($value);
                continue;
            }

            /* initiator */
            if ($key === 'initiator_id') {
                $result['Initiator'] = self::getUserName($value);
                continue;
            }

            /* process */
            if ($key === 'process_id') {
                $result['Process'] = self::getProcessName($value);
                continue;
            }

            /* stage */
            if ($key === 'stage_id') {
                $result['Stage'] = self::getStageName($value);
                continue;
            }

            /* nested array */
            if (is_array($value)) {
                $label = FieldLabelHelper::getLabel($module, $key);
                $result[$label] = self::cleanNestedValue($value);

                continue;
            }

            /* JSON stored as string */
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $label = FieldLabelHelper::getLabel($module, $key);
                    $result[$label] = self::cleanNestedValue($decoded);

                    continue;
                }
            }

            /* normal field */
            $label = FieldLabelHelper::getLabel($module, $key);
            $result[$label] = $value;
        }
        return $result;
    }

    /* Clean nested JSON / array values */
    private static function cleanNestedValue($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $result[$key] = self::cleanNestedValue($item);
                continue;
            }

            if (is_object($item)) {
                $result[$key] = self::cleanNestedValue(
                    $item->toArray()
                );
                continue;
            }

            if (is_string($item)) {
                $decoded = json_decode($item, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $result[$key] = self::cleanNestedValue($decoded);
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
}