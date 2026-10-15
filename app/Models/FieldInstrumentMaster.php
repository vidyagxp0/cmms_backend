<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldInstrumentMaster extends Model
{
    protected $table = 'field_instrument_masters';

    protected $fillable = [
        'equipment_name',
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
        'stage_id',
        'process_id'
    ];

    protected $casts = [
        'checklist_config' => 'array',
    ];

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    public function stage()
    {
        return $this->belongsTo(Stage::class);
    }
}
