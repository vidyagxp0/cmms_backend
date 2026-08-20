<?php

namespace App\Helpers;

use App\Models\Audit;
use Illuminate\Support\Facades\Auth;

class AuditHelper
{
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
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /* formate values */
    public static function formatValue($value, $module)
    {
        if (!$value) {
            return null;
        }

        $labels = self::getFieldLabels($module);

        $formattedValue = [];

        foreach ($value as $key => $data) {
            $label = $labels[$key] ?? $key;
            if ($key === 'is_active') {
                $data = $data ? 'Active' : 'Inactive';
            }
            $formattedValue[$label] = $data;
        }
        return $formattedValue;
    }

    /* get fields label */
    private static function getFieldLabels($module)
    {
        return match ($module) {

            'Department' => [
                'name' => 'Name',
                'is_active' => 'Active Status',
            ],

            'Role' => [
                'name' => 'Role Name',
                'is_active' => 'Active Status',
            ],

            'User' => [
                'salutation' => 'Salutation',
                'person_id' => 'Person ID',
                'name' => 'Name',
                'username' => 'Username',
                'email' => 'Email',
                'mobile_no' => 'Mobile Number',
                'department_id' => 'Department',
            ],

            default => [],
        };
    }
}