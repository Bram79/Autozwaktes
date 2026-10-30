<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();      // display name, e.g. "Volkswagen"
            $table->string('slug')->unique();      // url part, e.g. "volkswagen"
            $table->string('rdw_name')->unique();  // exact uppercase value in the RDW data, e.g. "VOLKSWAGEN"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};
