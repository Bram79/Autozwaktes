<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defect_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary(); // gebrek_identificatie, e.g. "576"
            $table->text('description');
            $table->string('article_number')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defect_codes');
    }
};
