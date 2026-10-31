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
        $key = 'stats:top:'.RankingService::lastInspectedYear().":{$generation->id}:".($year ?? 'all').':'.($fuel ?? 'all');

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

    /**
     * How a generation (or one build year of it) scores against the other models, on the defects that are common.
     * "Weak" is clearly more often than the average, "strong" clearly less often.
     *
     * @return array{enough_data: bool, total: int, weak: array, strong: array} each point: code, description, vehicles, percentage, average, ratio
     */
    public function comparison(Generation $generation, ?int $year = null): array
    {
        return Cache::rememberForever('stats:compare:'.RankingService::lastInspectedYear().":{$generation->id}:".($year ?? 'all'), fn () => $this->calculateComparison($generation, $year));
    }

    /** Average catalogue price (the new price according to the RDW) of a generation or one build year, if the RDW has enough prices. */
    public function newPrice(Generation $generation, ?int $year = null): ?array
    {
        return Cache::rememberForever("stats:price:{$generation->id}:".($year ?? 'all'), function () use ($generation, $year) {
            $rows = DB::table('model_prices')->where('car_model_id', $generation->car_model_id)->whereBetween('build_year', $this->yearRange($generation));
            $year && $rows->where('build_year', $year);
            $row = $rows->selectRaw('sum(vehicles) as cars, sum(vehicles * average_price) as total')->first();

            // fewer than 20 prices is not an average worth showing
            return ($row->cars ?? 0) >= 20 ? ['average' => (int) round($row->total / $row->cars), 'vehicles' => (int) $row->cars] : null;
        });
    }

    /**
     * The cheapest and the most expensive build year of a generation by catalogue price. A generation can cover many years,
     * and the price of a 2011 car is not the price of a 2024 car, so there is no single average. Years with fewer than 20 prices are left out.
     *
     * @return array{van: array{bouwjaar: int, euro: int}, tot: array{bouwjaar: int, euro: int}}|null
     */
    public function newPriceRange(Generation $generation): ?array
    {
        return Cache::rememberForever("stats:price-range:{$generation->id}", function () use ($generation) {
            $years = DB::table('model_prices')->where('car_model_id', $generation->car_model_id)
                ->whereBetween('build_year', $this->yearRange($generation))->where('vehicles', '>=', 20)
                ->orderBy('average_price')->orderBy('build_year')->get(['build_year', 'average_price']);

            if ($years->isEmpty()) {
                return null;
            }

            $cheapest = $years->first();
            $dearest = $years->last();

            return [
                'van' => ['bouwjaar' => (int) $cheapest->build_year, 'euro' => (int) $cheapest->average_price],
                'tot' => ['bouwjaar' => (int) $dearest->build_year, 'euro' => (int) $dearest->average_price],
            ];
        });
    }

    private function calculateComparison(Generation $generation, ?int $year): array
    {
        $none = ['enough_data' => false, 'total' => 0, 'weak' => [], 'strong' => []];
        $range = $this->inspectedRange($generation);

        $scope = function ($query, string $table) use ($range, $year) {
            $query->whereBetween("{$table}.build_year", $range);
            $year && $query->where("{$table}.build_year", $year);

            return $query;
        };

        $total = (int) $scope(DB::table('vehicle_counts')->where('car_model_id', $generation->car_model_id), 'vehicle_counts')->sum('total_vehicles');
        $othersTotal = (int) $scope(DB::table('vehicle_counts')->where('car_model_id', '!=', $generation->car_model_id), 'vehicle_counts')->sum('total_vehicles');

        if ($total < config('site.min_sample') || $othersTotal < config('site.min_sample')) {
            return $none;
        }

        $own = $scope(DB::table('defect_stats')->where('generation_id', $generation->id), 'defect_stats')
            ->groupBy('defect_code')->selectRaw('defect_code, sum(vehicles_with_defect) as n')->pluck('n', 'defect_code');
        $others = $scope(DB::table('defect_stats')->where('car_model_id', '!=', $generation->car_model_id), 'defect_stats')
            ->groupBy('defect_code')->selectRaw('defect_code, sum(vehicles_with_defect) as n')->pluck('n', 'defect_code');
        $descriptions = DB::table('defect_codes')->whereIn('code', $own->keys()->merge($others->keys())->unique())->pluck('description', 'code');

        $weak = [];
        $strong = [];

        foreach ($others as $code => $n) {
            $average = $n / $othersTotal * 100;
            $vehicles = (int) ($own[$code] ?? 0);
            $percentage = $vehicles / $total * 100;

            // only defects that are common elsewhere, otherwise a ratio says nothing
            if ($average < config('site.compare.min_average') || ! isset($descriptions[$code])) {
                continue;
            }

            $point = [
                'code' => (string) $code, 'description' => $descriptions[$code], 'vehicles' => $vehicles,
                'percentage' => round($percentage, 1), 'average' => round($average, 1), 'ratio' => round($percentage / $average, 1),
            ];

            if ($vehicles >= config('site.compare.min_vehicles') && $point['ratio'] >= config('site.compare.ratio_high')) {
                $weak[] = $point;
            } elseif ($point['ratio'] <= config('site.compare.ratio_low')) {
                $strong[] = $point;
            }
        }

        usort($weak, fn ($a, $b) => $b['ratio'] <=> $a['ratio']);
        usort($strong, fn ($a, $b) => $a['ratio'] <=> $b['ratio']);

        return ['enough_data' => true, 'total' => $total, 'weak' => array_slice($weak, 0, 3), 'strong' => array_slice($strong, 0, 3)];
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

    /**
     * The build years of a generation that have had an APK. Newer cars have no defects yet, and counting them
     * would pull every percentage down (a new Golf generation has thousands of cars that were never inspected).
     */
    public function inspectedRange(Generation $generation): array
    {
        return [$generation->year_from, min($generation->year_to ?? (int) date('Y'), RankingService::lastInspectedYear())];
    }

    private function calculateTopDefects(Generation $generation, ?int $year, ?string $fuel): array
    {
        $apply = function ($query) use ($generation, $year, $fuel) {
            $query->whereBetween('build_year', $this->inspectedRange($generation));
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
                ->whereBetween('build_year', $this->inspectedRange($generation));
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
