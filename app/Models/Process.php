<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Process extends Model
{
    protected $fillable = [
        'name',
        'is_child',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_child' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function stages()
    {
        return $this->hasMany(Stage::class);
    }
}