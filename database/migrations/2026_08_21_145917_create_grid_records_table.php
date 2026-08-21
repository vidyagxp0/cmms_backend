<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grid_records', function (Blueprint $table) {
            $table->id();

            $table->foreignId('process_record_id')->constrained('process_records')->onDelete('cascade');
            $table->longText('grid_data')->nullable();
            $table->timestamps();

            $table->index('process_record_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grid_records');
    }
};