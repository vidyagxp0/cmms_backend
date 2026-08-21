<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChecklistRecord extends Model
{
    protected $fillable = [
        'process_record_id',
        'checklist_data',
    ];

    protected function casts(): array
    {
        return [
            'checklist_data' => 'array',
        ];
    }

    public function processRecord()
    {
        return $this->belongsTo(ProcessRecord::class);
    }
}