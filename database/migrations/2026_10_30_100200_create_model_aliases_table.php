<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Maps messy RDW "handelsbenaming" values to a car model.
        // pattern is a regex matched against the uppercase value, e.g. "^A4( |$)".
        Schema::create('model_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->unsignedSmallInteger('priority')->default(100); // lower number is checked first
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_aliases');
    }
};
