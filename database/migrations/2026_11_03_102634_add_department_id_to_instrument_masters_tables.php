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
            'process_records'
        ] as $tableName) {
            if (
                Schema::hasTable($tableName) &&
                !Schema::hasColumn($tableName, 'department_id')
            ) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->unsignedBigInteger('department_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'instrument_masters',
            'field_instrument_masters',
            'spare_instrument_masters',
            'process_records'
        ] as $tableName) {
            if (
                Schema::hasTable($tableName) &&
                Schema::hasColumn($tableName, 'department_id')
            ) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropIndex(['department_id']);
                    $table->dropColumn('department_id');
                });
            }
        }
    }
};