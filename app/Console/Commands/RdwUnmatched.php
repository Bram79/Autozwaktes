<?php

namespace App\Console\Commands;

use App\Rdw\StatisticsCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RdwUnmatched extends Command
{
    protected $signature = 'rdw:unmatched {--limit=25}';

    protected $description = 'Show the most common RDW model names that match none of our models (to improve the aliases)';

    public function handle(StatisticsCalculator $calculator): int
    {
        $calculator->mapModels();

        $rows = DB::table('staging_vehicles')
            ->whereNull('car_model_id')
            ->selectRaw('brand_raw, model_raw, count(*) as vehicles')
            ->groupBy('brand_raw', 'model_raw')
            ->orderByDesc('vehicles')
            ->limit((int) $this->option('limit'))
            ->get();

        $this->table(['Brand', 'Model (RDW)', 'Vehicles'], $rows->map(fn ($r) => (array) $r)->all());

        return self::SUCCESS;
    }
}
