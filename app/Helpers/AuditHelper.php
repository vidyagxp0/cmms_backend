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

            /* for handling status */
            if ($key === 'is_active') {
                $data = $data ? 'Active' : 'Inactive';
            }

            /* for handling user roles */
            if (is_array($data) && $key !== 'permissions') {
                $data = implode(' | ', $data);
            }
            if ($key === 'permissions' && is_array($data)) {
                $permissionData = $data[0] ?? [];
                $permissions = [];
                foreach ($permissionData as $permission => $allowed) {
                    $permissions[] = ucfirst($permission) . ': ' . ($allowed ? 'Yes' : 'No');
                }
                $data = implode(', ', $permissions);
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
                'department' => 'Department',
                'permissions' => "Permissions"
            ],

            'User' => [
                'salutation' => 'Salutation',
                'person_id' => 'Person ID',
                'name' => 'Name',
                'username' => 'Username',
                'email' => 'Email',
                'mobile_no' => 'Mobile Number',
                'department' => 'Department',
                'roles' => 'Assigned Roles',
            ],

            default => [],
        };
    }
}