<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('process_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_id')->constrained('processes')->onDelete('cascade');
            $table->foreignId('stage_id')->constrained('stages')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('initiator_id')->constrained('users')->onDelete('cascade');
            $table->text('short_description')->nullable();
            $table->text('initiation_date')->nullable();
            $table->longText('process_data')->nullable();
            $table->timestamps();

            $table->index('process_id');
            $table->index('stage_id');
            $table->index('department_id');
            $table->index('initiator_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_records');
    }
};