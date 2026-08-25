<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('record_activity_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_record_id')->constrained('process_records')->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('activities')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('stage_id')->constrained('stages')->restrictOnDelete();
            $table->foreignId('target_stage')->constrained('stages')->restrictOnDelete();
            $table->text('comment')->nullable();
            $table->timestamp('performed_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_activity_histories');
    }
};