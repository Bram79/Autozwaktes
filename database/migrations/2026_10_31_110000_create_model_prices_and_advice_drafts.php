<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Average catalogue price (the new price according to the RDW) per model and build year.
        Schema::create('model_prices', function (Blueprint $table) {
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('build_year');
            $table->unsignedInteger('vehicles');       // cars that have a catalogue price
            $table->unsignedInteger('average_price');  // euros

            $table->primary(['car_model_id', 'build_year']);
        });

        // Texts written by the AI from our own statistics. Nothing goes live until a person approves it.
        Schema::create('advice_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generation_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->json('facts');                          // the numbers the text was written from
            $table->string('ai_model', 60)->nullable();
            $table->string('status', 10)->default('concept')->index();   // concept, approved, rejected
            $table->string('note')->nullable();             // why a draft was rejected automatically
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advice_drafts');
        Schema::dropIfExists('model_prices');
    }
};
