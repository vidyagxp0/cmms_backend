<?php

namespace App\Helpers;

class FieldLabelHelper
{
    public static function getLabel(
        string $module,
        string $field
    ): string {
        $labels = [

            /* Equipment Master */
            'Equipment Master' => [
                'name' => 'Name',
                'equipment_id' => 'Equipment ID',
                'make' => 'Make',
                'model' => 'Model',
                'equipment_type' => 'Equipment Type',
            ],

            /* Calibration Planner */
            'Calibration Planner' => [
                
            ],

            /* Preventive Maintenance Planner */
            'Preventive Maintenance Planner' => [
                
            ],

            /* Preventive Maintenance */
            'Preventive Maintenance' => [
                
            ],

            /* Calibration Management */
            'Calibration Management' => [
                
            ],
        ];

        return $labels[$module][$field] ?? $field;
    }
}