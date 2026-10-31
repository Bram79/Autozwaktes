<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "The average of all other models" filters on build year first. Without this index every such calculation scans the whole table.
        Schema::table('defect_stats', function (Blueprint $table) {
            $table->index(['build_year', 'defect_code'], 'defect_stats_year_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('defect_stats', function (Blueprint $table) {
            $table->dropIndex('defect_stats_year_code_index');
        });
    }
};
