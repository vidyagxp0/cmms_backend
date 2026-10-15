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
        Schema::table('instrument_masters', function (Blueprint $table) {
            $table->longText('hod_review_comments')->nullable();
            $table->longText('hod_review_attachment')->nullable();
            $table->longText('qa_review_comments')->nullable();
            $table->longText('qa_review_attachment')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('instrument_masters', function (Blueprint $table) {
            //
        });
    }
};
