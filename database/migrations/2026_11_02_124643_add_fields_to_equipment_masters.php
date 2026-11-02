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
        Schema::table('equipment_masters', function (Blueprint $table) {
            $table->text('equipment_name')->nullable();
            $table->text('equipment_id_no')->nullable();
            $table->text('equipment_make')->nullable();
            $table->text('equipment_model')->nullable();
            $table->text('equipment_location')->nullable();
            $table->text('asset_code')->nullable();
            $table->text('capacity')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('equipment_masters', function (Blueprint $table) {
            //
        });
    }
};
