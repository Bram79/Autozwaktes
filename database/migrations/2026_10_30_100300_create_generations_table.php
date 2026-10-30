<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_model_id')->constrained()->cascadeOnDelete();
            $table->string('code');                              // e.g. "B8"
            $table->string('slug');                              // e.g. "b8"
            $table->unsignedSmallInteger('year_from');
            $table->unsignedSmallInteger('year_to')->nullable(); // null = still in production
            $table->text('buying_advice')->nullable();           // written by hand, shown on the site
            $table->timestamps();

            $table->unique(['car_model_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
