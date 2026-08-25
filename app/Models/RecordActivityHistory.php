<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordActivityHistory extends Model
{
    protected $fillable = [
        'process_record_id',
        'activity_id',
        'performed_by',
        'stage_id',
        'target_stage',
        'comment',
        'performed_at',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
        ];
    }

    /* process record */
    public function processRecord()
    {
        return $this->belongsTo(ProcessRecord::class,'process_record_id');
    }

    /* activity */
    public function activity()
    {
        return $this->belongsTo(Activity::class,'activity_id');
    }

    /* user who performed activity */
    public function performedBy()
    {
        return $this->belongsTo(User::class,'performed_by');
    }

    /* stage from which activity was performed */
    public function stage()
    {
        return $this->belongsTo(Stage::class,'stage_id');
    }

    /* target stage */
    public function targetStage()
    {
        return $this->belongsTo(Stage::class,'target_stage_id');
    }
}