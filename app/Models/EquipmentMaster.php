<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaster extends Model
{
    protected $fillable = [
        'instrument_name',
        'instrument_id_no',
        'make',
        'sr_no_model_no',
        'location',
        'least_count',
        'accuracy',
        'instrument_range',
        'operating_range',
        'calibration_test_points',
        'category',
        'checklist_config',
    ];

    protected $casts = [
        'checklist_config' => 'array',
    ];
}
