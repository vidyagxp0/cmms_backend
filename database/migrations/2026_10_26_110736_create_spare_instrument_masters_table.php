<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('spare_instrument_masters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->onDelete('cascade');
            $table->foreignId('process_id')->constrained('processes')->onDelete('cascade');
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade');
            $table->string('instrument_name');
            $table->string('instrument_id_no')->unique();
            $table->string('make');
            $table->string('sr_no_model_no');
            $table->string('location');
            $table->string('least_count');
            $table->string('accuracy');
            $table->string('instrument_range');
            $table->string('operating_range');
            $table->text('calibration_test_points');
            $table->longText('hod_review_comments')->nullable();
            $table->longText('hod_review_attachment')->nullable();
            $table->longText('qa_review_comments')->nullable();
            $table->longText('qa_review_attachment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_instrument_masters');
    }
};
