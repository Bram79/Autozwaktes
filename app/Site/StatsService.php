<?php

namespace App\Site;

use App\Models\CarModel;
use App\Models\Generation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads the pre-calculated statistics for the site.
 * Results are cached until the next import clears the cache.
 */
class StatsService
{
    /**
     * Top defects for a generation, optionally for one build year and/or fuel.
     *
     * @return array{total: int, defects: Collection, enough_data: bool}
     */
    public function topDefects(Generation $generation, ?int $year = null, ?string $fuel = null): array
    {
        $key = "stats:top:{$generation->id}:".($year ?? 'all').':'.($fuel ?? 'all');

        // only plain arrays go into the cache (objects can't be unserialized from it)
        $cached = Cache::rememberForever($key, function () use ($generation, $year, $fuel) {
            $result = $this->calculateTopDefects($generation, $year, $fuel);
            $result['defects'] = $result['defects']->map(fn ($d) => (array) $d)->all();

            return $result;
        });

        $cached['defects'] = collect($cached['defects'])->map(fn ($d) => (object) $d);

        return $cached;
    }

    /** Build years with enough data, newest first: [year => total vehicles]. */
    public function years(Generation $generation): array
    {
        return Cache::rememberForever("stats:years:{$generation->id}", function () use ($generation) {
            return DB::table('vehicle_counts')
                ->where('car_model_id', $generation->car_model_id)
                ->whereBetween('build_year', $this->yearRange($generation))
                ->groupBy('build_year')
                ->havingRaw('sum(total_vehicles) >= ?', [config('site.min_sample')])
                ->orderByDesc('build_year')
                ->selectRaw('build_year, sum(total_vehicles) as total')
                ->pluck('total', 'build_year')
                ->map(fn ($t) => (int) $t)
                ->all();
        });
    }

    /** Fuels with enough vehicles in this generation. */
    public function fuels(Generation $generation): array
    {
        return Cache::rememberForever("stats:fuels:{$generation->id}", function () use ($generation) {
            return DB::table('vehicle_counts')
                ->where('car_model_id', $generation->car_model_id)
                ->whereBetween('build_year', $this->yearRange($generation))
                ->groupBy('fuel')
                ->havingRaw('sum(total_vehicles) >= ?', [config('site.min_sample')])
                ->orderByDesc(DB::raw('sum(total_vehicles)'))
                ->pluck('fuel')
                ->all();
        });
    }

    /** Models with the most vehicles in the register. */
    public function popularModels(int $limit = 10): Collection
    {
        $ids = collect(Cache::rememberForever("stats:popular:{$limit}", fn () => DB::table('vehicle_counts')
            ->groupBy('car_model_id')
            ->orderByDesc(DB::raw('sum(total_vehicles)'))
            ->limit($limit)
            ->pluck('car_model_id')
            ->all()));

        return CarModel::with('brand')->whereIn('id', $ids)->get()
            ->sortBy(fn ($m) => $ids->search($m->id))->values();
    }

    /** Generations of a model that have statistics. */
    public function generationsWithData(CarModel $model): Collection
    {
        $withData = Cache::rememberForever("stats:model-generations:{$model->id}", fn () => DB::table('defect_stats')
            ->where('car_model_id', $model->id)->distinct()->pluck('generation_id')->all());

        return $model->generations()->get()->filter(fn ($g) => in_array($g->id, $withData))->values();
    }

    /** First and last build year of a generation as shown on the site. */
    public function yearRange(Generation $generation): array
    {
        return [$generation->year_from, $generation->year_to ?? (int) date('Y')];
    }

    private function calculateTopDefects(Generation $generation, ?int $year, ?string $fuel): array
    {
        $apply = function ($query) use ($generation, $year, $fuel) {
            $query->whereBetween('build_year', $this->yearRange($generation));
            $year && $query->where('build_year', $year);
            $fuel && $query->where('fuel', $fuel);

            return $query;
        };

        $total = (int) $apply(DB::table('vehicle_counts')->where('car_model_id', $generation->car_model_id))->sum('total_vehicles');

        if ($total < config('site.min_sample')) {
            return ['total' => $total, 'defects' => collect(), 'enough_data' => false];
        }

        $top = $apply(DB::table('defect_stats as s')->where('s.car_model_id', $generation->car_model_id)->where('s.generation_id', $generation->id))
            ->join('defect_codes as c', 'c.code', '=', 's.defect_code')
            ->groupBy('s.defect_code', 'c.description')
            ->orderByDesc(DB::raw('sum(s.vehicles_with_defect)'))
            ->limit(config('site.top_defects'))
            ->selectRaw('s.defect_code as code, c.description, sum(s.vehicles_with_defect) as with_defect')
            ->get();

        $average = $this->averageForOthers($generation, $year, $fuel, $top->pluck('code')->all());

        $defects = $top->map(fn ($row) => (object) [
            'code' => $row->code,
            'description' => $row->description,
            'vehicles' => (int) $row->with_defect,
            'percentage' => round($row->with_defect / $total * 100, 1),
            'average' => $average[$row->code] ?? null,
        ]);

        return ['total' => $total, 'defects' => $defects, 'enough_data' => true];
    }

    /**
     * Average percentage of the same defects for all other tracked models built in the same years.
     *
     * @return array<string, float> defect code => percentage
     */
    private function averageForOthers(Generation $generation, ?int $year, ?string $fuel, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $apply = function ($query) use ($generation, $year, $fuel) {
            $query->where('car_model_id', '!=', $generation->car_model_id)
                ->whereBetween('build_year', $this->yearRange($generation));
            $year && $query->where('build_year', $year);
            $fuel && $query->where('fuel', $fuel);

            return $query;
        };

        $total = (int) $apply(DB::table('vehicle_counts'))->sum('total_vehicles');

        if ($total < config('site.min_sample')) {
            return [];
        }

        return $apply(DB::table('defect_stats'))
            ->whereIn('defect_code', $codes)
            ->groupBy('defect_code')
            ->selectRaw('defect_code, sum(vehicles_with_defect) as with_defect')
            ->pluck('with_defect', 'defect_code')
            ->map(fn ($n) => round($n / $total * 100, 1))
            ->all();
    }
}
