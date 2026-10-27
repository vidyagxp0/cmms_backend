<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach ([
            'instrument_masters',
            'field_instrument_masters',
            'spare_instrument_masters',
        ] as $tableName) {
            if (
                Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, 'stage_id')
            ) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('stage_id')->nullable()->change();
                });
            }
        }
    }

    public function down(): void
    {
        
    }
};