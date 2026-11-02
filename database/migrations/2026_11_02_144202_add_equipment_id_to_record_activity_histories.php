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
        Schema::table('record_activity_histories', function (Blueprint $table) {
            $table->foreignId('equipment_id')->nullable()->constrained('equipment_masters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('record_activity_histories', function (Blueprint $table) {
            //
        });
    }
};
