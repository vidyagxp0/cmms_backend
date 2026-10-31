<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instrument_masters', function (Blueprint $table) {
            $table->text('asset_code')->nullable();
            $table->text('capacity')->nullable();
        });

        Schema::table('field_instrument_masters', function (Blueprint $table) {
            $table->text('asset_code')->nullable();
            $table->text('capacity')->nullable();
        });

        Schema::table('spare_instrument_masters', function (Blueprint $table) {
            $table->text('asset_code')->nullable();
            $table->text('capacity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('instrument_masters', function (Blueprint $table) {
            $table->dropColumn(['asset_code', 'capacity']);
        });

        Schema::table('field_instrument_masters', function (Blueprint $table) {
            $table->dropColumn(['asset_code', 'capacity']);
        });

        Schema::table('spare_instrument_masters', function (Blueprint $table) {
            $table->dropColumn(['asset_code', 'capacity']);
        });
    }
};