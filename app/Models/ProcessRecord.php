<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProcessRecord extends Model
{
    protected $fillable = [
        'process_id',
        'stage_id',
        'department_id',
        'initiator_id',
        'short_description',
        'initiation_date',
        'process_data',
    ];

    protected function casts(): array
    {
        return [
            'process_data' => 'array',
        ];
    }

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

    public function gridRecords()
    {
        return $this->hasMany(GridRecord::class);
    }

    public function checklistRecords()
    {
        return $this->hasMany(ChecklistRecord::class);
    }
}