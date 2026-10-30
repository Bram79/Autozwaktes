<?php

namespace App\Rdw;

use App\Models\Generation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Turns the raw staging tables into the pre-calculated tables the site reads.
 *
 * percentage = unique vehicles with this defect / vehicles in the register
 *              (same model, build year and fuel) * 100
 */
class StatisticsCalculator
{
    /** SQL: one fuel label per vehicle. A car with electricity next to another fuel is a hybrid. */
    private static function fuelSql(string $prefix = ''): string
    {
        $fuel = "{$prefix}fuel";
        $fuel2 = "{$prefix}fuel_2";

        return "CASE WHEN {$fuel2} IS NOT NULL AND ({$fuel} = 'Elektriciteit' OR {$fuel2} = 'Elektriciteit') THEN 'Hybride' ELSE COALESCE({$fuel}, 'Onbekend') END";
    }

    public function __construct(private ModelNormalizer $normalizer)
    {
    }

    public function run(): void
    {
        $this->mapModels();

        DB::transaction(function () {
            $this->calculateVehicleCounts();
            $this->calculateDefectStats();
        });

        Cache::flush();
        Log::info('RDW statistics recalculated and cache cleared.');
    }

    public static function percentage(int $withDefect, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(min($withDefect, $total) / $total * 100, 2);
    }

    /** Link every staging vehicle to one of our car models (or null). */
    public function mapModels(): void
    {
        $pairs = DB::table('staging_vehicles')->select('brand_raw', 'model_raw')->distinct()->get();

        foreach ($pairs as $pair) {
            DB::table('staging_vehicles')
                ->where('brand_raw', $pair->brand_raw)
                ->where('model_raw', $pair->model_raw)
                ->update(['car_model_id' => $this->normalizer->match($pair->brand_raw, $pair->model_raw)]);
        }
    }

    private function calculateVehicleCounts(): void
    {
        DB::table('defect_stats')->delete();
        DB::table('vehicle_counts')->delete();

        $fuel = self::fuelSql();

        DB::table('vehicle_counts')->insertUsing(
            ['car_model_id', 'build_year', 'fuel', 'total_vehicles'],
            DB::table('staging_vehicles')
                ->whereNotNull('car_model_id')
                ->whereNotNull('build_year')
                ->selectRaw("car_model_id, build_year, {$fuel} as fuel, count(*) as total_vehicles")
                ->groupByRaw("car_model_id, build_year, {$fuel}")
        );
    }

    private function calculateDefectStats(): void
    {
        $totals = [];
        foreach (DB::table('vehicle_counts')->get() as $row) {
            $totals["{$row->car_model_id}|{$row->build_year}|{$row->fuel}"] = $row->total_vehicles;
        }

        $generations = Generation::all()->groupBy('car_model_id');

        $fuel = self::fuelSql('v.');

        $rows = DB::table('staging_defects as d')
            ->join('staging_vehicles as v', 'v.license_plate', '=', 'd.license_plate')
            ->whereNotNull('v.car_model_id')
            ->whereNotNull('v.build_year')
            ->selectRaw("v.car_model_id, v.build_year, {$fuel} as fuel, d.defect_code, count(distinct d.license_plate) as vehicles_with_defect")
            ->groupByRaw("v.car_model_id, v.build_year, {$fuel}, d.defect_code")
            ->cursor();

        $batch = [];
        foreach ($rows as $row) {
            $total = $totals["{$row->car_model_id}|{$row->build_year}|{$row->fuel}"] ?? 0;

            $generation = ($generations[$row->car_model_id] ?? collect())
                ->first(fn (Generation $g) => $g->coversYear($row->build_year));

            $batch[] = [
                'car_model_id' => $row->car_model_id,
                'generation_id' => $generation?->id,
                'build_year' => $row->build_year,
                'fuel' => $row->fuel,
                'defect_code' => $row->defect_code,
                'vehicles_with_defect' => $row->vehicles_with_defect,
                'total_vehicles' => $total,
                'percentage' => self::percentage($row->vehicles_with_defect, $total),
            ];

            if (count($batch) === 1000) {
                DB::table('defect_stats')->insert($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            DB::table('defect_stats')->insert($batch);
        }
    }
}
