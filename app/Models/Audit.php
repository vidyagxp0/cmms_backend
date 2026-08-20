<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model
{
    protected $fillable = [
        'user_id',
        'module',
        'action',
        'description',
        'record_id',
        'old_value',
        'new_value',
        'model'
    ];

    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}