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
            'Instrument Master' => [
                'instrument_name' => 'Instrument Name',
                'instrument_id_no' => 'Instrument ID No.',
                'make' => 'Make',
                'sr_no_model_no' => 'Sr. No. / Model No.',
                'location' => 'Location',
                'least_count' => 'Least Count',
                'accuracy' => 'Accuracy',
                'instrument_range' => 'Instrument Range',
                'operating_range' => 'Operating Range',
                'calibration_test_points' => 'Calibration Test Points',
                'category' => 'Category',
                'checklist_config' => 'Checklist Configuration',
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