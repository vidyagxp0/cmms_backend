<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Activity extends Model
{
    protected $fillable = [
        'name',
        'from_stage',
        'to_stage',
        'is_active',
        'assigned_role',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function fromStage()
    {
        return $this->belongsTo(Stage::class, 'from_stage');
    }

    public function toStage()
    {
        return $this->belongsTo(Stage::class, 'to_stage');
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'assigned_role');
    }
}