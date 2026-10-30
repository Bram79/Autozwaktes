<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Pre-calculated numbers. These are the only tables the site reads from.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('build_year');
            $table->string('fuel', 30);
            $table->unsignedInteger('total_vehicles');

            $table->unique(['car_model_id', 'build_year', 'fuel']);
        });

        Schema::create('defect_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('build_year');
            $table->string('fuel', 30);
            $table->string('defect_code', 10);
            $table->unsignedInteger('vehicles_with_defect');
            $table->unsignedInteger('total_vehicles');
            $table->decimal('percentage', 5, 2); // vehicles_with_defect / total_vehicles * 100

            $table->unique(['car_model_id', 'build_year', 'fuel', 'defect_code'], 'defect_stats_unique');
            $table->index(['generation_id', 'defect_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_stats');
        Schema::dropIfExists('vehicle_counts');
    }
};
