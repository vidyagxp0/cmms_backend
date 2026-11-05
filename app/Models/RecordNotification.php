<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordNotification extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id',
        'record_type',
        'record_id',
        'process_id',
        'activity_id',
        'stage_id',
        'role_name',
        'title',
        'message',
        'status',
        'performed_by',
        'completed_by',
        'completed_at',
        'read_at',
        'mailed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
        'read_at' => 'datetime',
        'mailed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }
}