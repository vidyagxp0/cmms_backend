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
        Schema::table('users', function (Blueprint $table) {            
            $table->text('salutation')->nullable()->after('id');
            $table->text('person_id')->nullable()->after('salutation');
            $table->text('username')->nullable()->after('name');
            $table->text('mobile_no')->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->constrained('departments')->onDelete('cascade')->after('mobile_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
