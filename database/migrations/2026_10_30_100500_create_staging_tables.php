<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Raw import data. Only used to calculate statistics, never shown on the site.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_vehicles', function (Blueprint $table) {
            $table->string('license_plate', 10)->primary();
            $table->string('brand_raw');
            $table->string('model_raw');
            $table->unsignedSmallInteger('build_year')->nullable(); // year of first registration
            $table->string('fuel', 30)->nullable();                 // brandstof_volgnummer 1
            $table->string('fuel_2', 30)->nullable();               // brandstof_volgnummer 2 (hybrids)
            $table->foreignId('car_model_id')->nullable();          // filled in by the model mapping step

            $table->index(['brand_raw', 'model_raw']);
            $table->index(['car_model_id', 'build_year']);
        });

        Schema::create('staging_defects', function (Blueprint $table) {
            $table->id();
            $table->string('license_plate', 10);
            $table->string('defect_code', 10);
            $table->dateTime('reported_at');
            $table->unsignedSmallInteger('defect_count')->default(1);

            // makes re-running an import safe: the same row can't be added twice
            $table->unique(['license_plate', 'defect_code', 'reported_at'], 'staging_defects_unique');
            $table->index('defect_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_defects');
        Schema::dropIfExists('staging_vehicles');
    }
};
