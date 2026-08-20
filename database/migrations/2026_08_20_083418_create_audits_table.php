<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('old_value')->nullable();
            $table->longText('new_value')->nullable();
            $table->text('module');
            $table->text('action');
            $table->text('description')->nullable();
            $table->string('model')->nullable();
            $table->unsignedBigInteger('record_id')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};