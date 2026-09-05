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
        'checklist_config'
    ];

    protected $casts = [
        'checklist_config' => 'array',
    ];
}