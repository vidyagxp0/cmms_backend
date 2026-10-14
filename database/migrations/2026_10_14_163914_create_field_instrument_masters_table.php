
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_instrument_masters', function (Blueprint $table) {
            $table->id();

            $table->string('equipment_name');
            $table->string('instrument_name');
            $table->string('instrument_id_no')->unique();
            $table->string('make');
            $table->string('sr_no_model_no');
            $table->string('least_count');
            $table->string('location');
            $table->string('accuracy');
            $table->string('instrument_range');
            $table->string('operating_range');
            $table->text('calibration_test_points');
            $table->string('category');

            $table->json('checklist_config')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_instrument_masters');
    }
};
