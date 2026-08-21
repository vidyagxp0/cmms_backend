<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GridRecord extends Model
{
    protected $fillable = [
        'process_record_id',
        'grid_data',
    ];

    protected function casts(): array
    {
        return [
            'grid_data' => 'array',
        ];
    }

    public function processRecord()
    {
        return $this->belongsTo(ProcessRecord::class);
    }
}