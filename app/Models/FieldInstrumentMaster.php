<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FieldInstrumentMaster extends Model
{
    protected $table = 'field_instrument_masters';

    protected $fillable = [
        'equipment_name',
        'instrument_name',
        'instrument_id_no',
        'make',
        'sr_no_model_no',
        'location',
        'least_count',
        'accuracy',
        'instrument_range',
        'operating_range',
        'calibration_test_points',
        'category',
        'checklist_config',
        'stage_id',
        'process_id',        
        'hod_review_comments',
        'hod_review_attachment',
        'qa_review_attachment',
        'qa_review_comments'
    ];

    protected $casts = [
        'checklist_config' => 'array',
        'hod_review_attachment' => 'array',
        'qa_review_attachment' => 'array',
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
