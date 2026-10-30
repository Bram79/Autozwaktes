<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // How many cars of each RDW "type" (the maker's type code, e.g. "6R" for a Polo) were first registered per year.
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);   // '' when the RDW has no type
            $table->unsignedSmallInteger('build_year');
            $table->unsignedInteger('vehicles');

            $table->primary(['car_model_id', 'type', 'build_year']);
        });

        Schema::table('car_models', function (Blueprint $table) {
            $table->string('wikidata_id', 20)->nullable()->after('slug');
        });

        Schema::create('generation_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable();
            $table->unsignedTinyInteger('confidence');           // 0-100
            $table->string('source', 20);                         // rdw, rdw+wikidata, wikidata
            $table->unsignedInteger('vehicles')->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 10)->default('pending')->index();   // pending, approved, rejected
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generation_proposals');
        Schema::table('car_models', fn (Blueprint $table) => $table->dropColumn('wikidata_id'));
        Schema::dropIfExists('vehicle_types');
    }
};
