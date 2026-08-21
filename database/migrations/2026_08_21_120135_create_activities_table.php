<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('from_stage')->constrained('stages')->onDelete('cascade');
            $table->foreignId('to_stage')->constrained('stages')->onDelete('cascade');
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_role')->nullable()->constrained('roles')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};