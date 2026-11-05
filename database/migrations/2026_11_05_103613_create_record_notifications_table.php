<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('record_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('record_type');
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('process_id')->nullable();
            $table->unsignedBigInteger('activity_id');
            $table->unsignedBigInteger('stage_id');
            $table->string('role_name');
            $table->string('title');
            $table->text('message');
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
            $table->index(['record_type', 'record_id', 'stage_id', 'status'], 'record_notifications_lookup_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('record_notifications');
    }
};