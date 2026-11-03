<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentMaster extends Model
{
    protected $fillable = [
        'name',
        'equipment_id',
        'make',
        'model',
        'equipment_type',
        'equipment_name',
        'equipment_id_no',
        'equipment_make',
        'equipment_model',
        'equipment_location',
        'asset_code',
        'capacity',
        'checklist_config',
        'stage_id',
        'process_id',
        'initiator_id',
        'department_id',
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

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }
}
