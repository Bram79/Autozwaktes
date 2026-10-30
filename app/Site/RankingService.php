<?php

namespace App\Site;

use App\Models\Generation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rankings of generations by how many APK defects they have, compared fairly.
 *
 * Three things would make a plain "defects per 100 cars" list unfair:
 * - A car only has defects after its first APK (after 4 years). A 2024 car looks perfect because nobody inspected it yet,
 *   so build years without an APK are left out completely.
 * - Old cars have more defects than young ones. So each generation is compared with the average of ALL cars of the same
 *   build year and fuel: the index is 100 for an average car, 80 is 20% better, 120 is 20% worse.
 * - Of very old cars only the best kept ones are left, so they look better than they are: only the last 20 build years count.
 */
class RankingService
{
    /** The newest build year that has had an APK. */
    public static function lastInspectedYear(): int
    {
        return now()->year - (int) config('site.first_apk_after_years', 4);
    }

    /** The oldest build year that is still ranked (only the best kept very old cars survive, so they would look too good). */
    public static function firstRankedYear(): int
    {
        return now()->year - (int) config('site.ranking_max_age', 20);
    }

    /**
     * All ranked generations for a scope, best (lowest index) first.
     *
     * @return array<int, array{brand: string, brand_slug: string, model: string, model_slug: string, code: string, generation_slug: string, years: string, total: int, score: float, index: float}>
     *                                                                                                                                                                                               score: defects per 100 cars, index: compared with the average of the same build years (100 = average)
     */
    public function scores(?int $brandId = null, ?string $fuel = null, ?int $year = null): array
    {
        $last = self::lastInspectedYear();
        $first = self::firstRankedYear();

        return Cache::rememberForever("ranking:{$first}:{$last}:{$brandId}:{$fuel}:{$year}", fn () => $this->calculate($first, $last, $brandId, $fuel, $year));
    }

    /** Fuels with enough cars for a ranking: [slug => name]. */
    public function fuels(): array
    {
        $last = self::lastInspectedYear();
        $first = self::firstRankedYear();

        return Cache::rememberForever("ranking:fuels:{$first}:{$last}", fn () => DB::table('vehicle_counts')
            ->whereBetween('build_year', [$first, $last])
            ->groupBy('fuel')->havingRaw('sum(total_vehicles) >= ?', [config('site.min_ranking_sample') * 4])
            ->orderByDesc(DB::raw('sum(total_vehicles)'))->pluck('fuel')
            ->mapWithKeys(fn ($fuel) => [Str::slug($fuel) => $fuel])->all());
    }

    /** Build years that have had an APK and have enough cars for a ranking, newest first. */
    public function years(): array
    {
        $last = self::lastInspectedYear();
        $first = self::firstRankedYear();

        return Cache::rememberForever("ranking:years:{$first}:{$last}", fn () => DB::table('vehicle_counts')
            ->whereBetween('build_year', [$first, $last])
            ->groupBy('build_year')->havingRaw('sum(total_vehicles) >= ?', [config('site.min_ranking_sample') * 4])
            ->orderByDesc('build_year')->pluck('build_year')->map(fn ($y) => (int) $y)->all());
    }

    private function calculate(int $first, int $last, ?int $brandId, ?string $fuel, ?int $year): array
    {
        if ($year !== null && ($year > $last || $year < $first)) {
            return [];
        }

        // what to expect from a car of this build year and fuel: the average of all cars of the site
        $allCars = DB::table('vehicle_counts')->whereBetween('build_year', [$first, $last])
            ->groupBy('build_year', 'fuel')->selectRaw('build_year, fuel, sum(total_vehicles) as cars')->get();
        $allDefects = DB::table('defect_stats')->whereBetween('build_year', [$first, $last])
            ->groupBy('build_year', 'fuel')->selectRaw('build_year, fuel, sum(vehicles_with_defect) as defects')->get()
            ->mapWithKeys(fn ($row) => ["{$row->build_year}|{$row->fuel}" => $row->defects]);

        $rate = [];
        foreach ($allCars as $row) {
            $rate["{$row->build_year}|{$row->fuel}"] = $row->cars > 0 ? ($allDefects["{$row->build_year}|{$row->fuel}"] ?? 0) / $row->cars : 0;
        }

        // cars and defects per generation, build year and fuel (only what is in this scope)
        $cars = DB::table('vehicle_counts as vc')
            ->join('generations as g', function ($join) {
                $join->on('g.car_model_id', '=', 'vc.car_model_id')->on('vc.build_year', '>=', 'g.year_from');
            })
            ->whereRaw('vc.build_year <= coalesce(g.year_to, 9999)')
            ->join('car_models as m', 'm.id', '=', 'vc.car_model_id')
            ->whereBetween('vc.build_year', [$first, $last])
            ->when($brandId, fn ($q) => $q->where('m.brand_id', $brandId))
            ->when($fuel, fn ($q) => $q->where('vc.fuel', $fuel))
            ->when($year, fn ($q) => $q->where('vc.build_year', $year))
            ->groupBy('g.id', 'vc.build_year', 'vc.fuel')
            ->selectRaw('g.id as generation_id, vc.build_year, vc.fuel, sum(vc.total_vehicles) as cars')
            ->get();

        $defects = DB::table('defect_stats as ds')
            ->join('car_models as m', 'm.id', '=', 'ds.car_model_id')
            ->whereNotNull('ds.generation_id')
            ->whereBetween('ds.build_year', [$first, $last])
            ->when($brandId, fn ($q) => $q->where('m.brand_id', $brandId))
            ->when($fuel, fn ($q) => $q->where('ds.fuel', $fuel))
            ->when($year, fn ($q) => $q->where('ds.build_year', $year))
            ->groupBy('ds.generation_id', 'ds.build_year', 'ds.fuel')
            ->selectRaw('ds.generation_id, ds.build_year, ds.fuel, sum(ds.vehicles_with_defect) as defects')
            ->get()
            ->mapWithKeys(fn ($row) => ["{$row->generation_id}|{$row->build_year}|{$row->fuel}" => $row->defects]);

        $totals = [];   // per generation: cars, defects and how many defects an average car of the same years would have
        foreach ($cars as $row) {
            $totals[$row->generation_id] ??= ['cars' => 0, 'defects' => 0, 'expected' => 0.0, 'has_defects' => false];
            $key = "{$row->generation_id}|{$row->build_year}|{$row->fuel}";

            $totals[$row->generation_id]['cars'] += $row->cars;
            $totals[$row->generation_id]['defects'] += $defects[$key] ?? 0;
            $totals[$row->generation_id]['expected'] += $row->cars * ($rate["{$row->build_year}|{$row->fuel}"] ?? 0);
            $totals[$row->generation_id]['has_defects'] = $totals[$row->generation_id]['has_defects'] || isset($defects[$key]);
        }

        $ranked = array_filter($totals, fn ($t) => $t['cars'] >= config('site.min_ranking_sample') && $t['has_defects'] && $t['expected'] > 0);

        return Generation::with('carModel.brand')->whereIn('id', array_keys($ranked))->get()
            ->map(fn (Generation $g) => [
                'brand' => $g->carModel->brand->name,
                'brand_slug' => $g->carModel->brand->slug,
                'model' => $g->carModel->name,
                'model_slug' => $g->carModel->slug,
                'code' => $g->code,
                'generation_slug' => $g->slug,
                'years' => Seo::yearsLabel($g),
                'total' => (int) $ranked[$g->id]['cars'],
                'score' => round($ranked[$g->id]['defects'] / $ranked[$g->id]['cars'] * 100, 1),
                'index' => round($ranked[$g->id]['defects'] / $ranked[$g->id]['expected'] * 100, 1),
            ])
            ->sortBy('index')->values()->all();
    }
}
