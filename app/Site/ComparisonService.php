<?php

namespace App\Site;

use App\Models\CarModel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Comparisons of two models. There are thousands of possible pairs, so only the sensible ones exist:
 * two models are compared when they were sold side by side (at least 3 build years in common, each with enough cars)
 * and cost about the same when new (catalogue prices from the RDW within a third of each other).
 * That price is our stand-in for "the same segment": the RDW data has no segment.
 */
class ComparisonService
{
    private const MIN_CARS_PER_YEAR = 300;

    private const MIN_SHARED_YEARS = 3;

    private const MAX_PRICE_RATIO = 1.33;

    private const PER_MODEL = 3;

    /**
     * The pairs that get a page: lists of two keys like "volkswagen-polo", in alphabetical order.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public function pairs(): array
    {
        return Cache::rememberForever('comparison:pairs:'.RankingService::lastInspectedYear(), fn () => $this->findPairs());
    }

    /** "volkswagen-polo-vs-opel-corsa" for two keys, in the canonical (alphabetical) order. */
    public static function slug(string $a, string $b): string
    {
        [$a, $b] = $a < $b ? [$a, $b] : [$b, $a];

        return "{$a}-vs-{$b}";
    }

    /** The two models of a pair slug, or null when that pair has no page. */
    public function find(string $pair): ?array
    {
        $parts = explode('-vs-', $pair);

        if (count($parts) !== 2 || ! in_array($parts, $this->pairs(), true)) {
            return null;
        }

        $models = CarModel::with('brand')->get()->keyBy(fn (CarModel $m) => $m->brand->slug.'-'.$m->slug);

        return isset($models[$parts[0]], $models[$parts[1]]) ? [$models[$parts[0]], $models[$parts[1]]] : null;
    }

    /** Up to four models to compare this model with: [['name' => 'Opel Corsa', 'slug' => '…-vs-…'], ...] */
    public function suggestions(CarModel $model): array
    {
        $key = $model->brand->slug.'-'.$model->slug;
        $names = Cache::rememberForever('comparison:names', fn () => CarModel::with('brand')->get()
            ->mapWithKeys(fn (CarModel $m) => [$m->brand->slug.'-'.$m->slug => $m->brand->name.' '.$m->name])->all());

        $suggestions = [];
        foreach ($this->pairs() as $pair) {
            if (in_array($key, $pair, true)) {
                $other = $pair[0] === $key ? $pair[1] : $pair[0];
                $suggestions[] = ['name' => $names[$other] ?? $other, 'slug' => self::slug(...$pair)];
            }
        }

        return array_slice($suggestions, 0, 4);
    }

    /**
     * Everything for one comparison page, over the build years that both models have.
     *
     * @return array{years: int[], models: array<int, array{total: int, score: float}>, defects: array}|null
     */
    public function compare(CarModel $a, CarModel $b): ?array
    {
        return Cache::rememberForever('comparison:'.RankingService::lastInspectedYear().":{$a->id}:{$b->id}", fn () => $this->calculate($a, $b));
    }

    private function calculate(CarModel $a, CarModel $b): ?array
    {
        $data = $this->modelData();
        $years = $this->sharedYears($data[$a->id] ?? [], $data[$b->id] ?? []);

        if (count($years) < self::MIN_SHARED_YEARS) {
            return null;
        }

        $models = [];
        foreach ([$a, $b] as $model) {
            $total = array_sum(array_map(fn ($y) => $data[$model->id][$y]['cars'], $years));
            $defects = array_sum(array_map(fn ($y) => $data[$model->id][$y]['defects'], $years));
            $models[$model->id] = ['total' => $total, 'score' => round($defects / $total * 100, 1)];
        }

        // percentage per defect, for both models, over the shared years
        $rows = DB::table('defect_stats')->whereIn('car_model_id', [$a->id, $b->id])->whereIn('build_year', $years)
            ->groupBy('car_model_id', 'defect_code')->selectRaw('car_model_id, defect_code, sum(vehicles_with_defect) as n')->get();

        $percentages = [];
        foreach ($rows as $row) {
            $percentages[$row->defect_code][$row->car_model_id] = round($row->n / $models[$row->car_model_id]['total'] * 100, 1);
        }

        // the defects that are most common in either model
        uasort($percentages, fn ($x, $y) => max($y) <=> max($x));
        $top = array_slice($percentages, 0, 8, true);
        $descriptions = DB::table('defect_codes')->whereIn('code', array_keys($top))->pluck('description', 'code');

        $defects = [];
        foreach ($top as $code => $perModel) {
            $defects[] = ['description' => $descriptions[$code] ?? $code, 'a' => $perModel[$a->id] ?? 0.0, 'b' => $perModel[$b->id] ?? 0.0];
        }

        return ['years' => array_values($years), 'models' => $models, 'defects' => $defects];
    }

    private function findPairs(): array
    {
        $data = $this->modelData();
        $keys = CarModel::with('brand')->get()->mapWithKeys(fn (CarModel $m) => [$m->id => $m->brand->slug.'-'.$m->slug])->all();
        $ids = array_keys($data);
        $candidates = [];   // [id] => [[other id, price ratio], ...]

        foreach ($ids as $i => $a) {
            foreach (array_slice($ids, $i + 1) as $b) {
                $years = $this->sharedYears($data[$a], $data[$b]);
                $ratio = $this->priceRatio($data[$a], $data[$b], $years);

                if (count($years) >= self::MIN_SHARED_YEARS && $ratio !== null && $ratio <= self::MAX_PRICE_RATIO) {
                    $candidates[$a][] = [$b, $ratio];
                    $candidates[$b][] = [$a, $ratio];
                }
            }
        }

        // every model keeps only the closest ones, so a popular model does not get hundreds of pages
        $pairs = [];
        foreach ($candidates as $id => $others) {
            usort($others, fn ($x, $y) => $x[1] <=> $y[1]);
            foreach (array_slice($others, 0, self::PER_MODEL) as [$other]) {
                $pair = [$keys[$id], $keys[$other]];
                sort($pair);
                $pairs[implode('|', $pair)] = $pair;
            }
        }

        ksort($pairs);

        return array_values($pairs);
    }

    /** Build years where both models have enough cars, and that have had an APK (younger cars have no defects yet). */
    private function sharedYears(array $a, array $b): array
    {
        $last = RankingService::lastInspectedYear();
        $enough = fn (array $data) => array_keys(array_filter($data, fn ($y, $year) => $year <= $last && $y['cars'] >= self::MIN_CARS_PER_YEAR && $y['defects'] > 0, ARRAY_FILTER_USE_BOTH));

        return array_values(array_intersect($enough($a), $enough($b)));
    }

    /** How much more expensive the dearer of two models was when new, over the shared years (1.0 = the same). Null without prices. */
    private function priceRatio(array $a, array $b, array $years): ?float
    {
        $average = function (array $data) use ($years) {
            $cars = $sum = 0;
            foreach ($years as $year) {
                if (isset($data[$year]['price'])) {
                    $cars += $data[$year]['cars'];
                    $sum += $data[$year]['cars'] * $data[$year]['price'];
                }
            }

            return $cars >= self::MIN_CARS_PER_YEAR * self::MIN_SHARED_YEARS ? $sum / $cars : null;
        };

        $x = $average($a);
        $y = $average($b);

        return $x && $y ? max($x, $y) / min($x, $y) : null;
    }

    /** @return array<int, array<int, array{cars: int, defects: int, price: int|null}>> [model id][year] */
    private function modelData(): array
    {
        $data = [];

        foreach (DB::table('vehicle_counts')->groupBy('car_model_id', 'build_year')->selectRaw('car_model_id, build_year, sum(total_vehicles) as cars')->get() as $row) {
            $data[$row->car_model_id][$row->build_year] = ['cars' => (int) $row->cars, 'defects' => 0, 'price' => null];
        }
        foreach (DB::table('defect_stats')->groupBy('car_model_id', 'build_year')->selectRaw('car_model_id, build_year, sum(vehicles_with_defect) as defects')->get() as $row) {
            if (isset($data[$row->car_model_id][$row->build_year])) {
                $data[$row->car_model_id][$row->build_year]['defects'] = (int) $row->defects;
            }
        }
        foreach (DB::table('model_prices')->get() as $row) {
            if (isset($data[$row->car_model_id][$row->build_year])) {
                $data[$row->car_model_id][$row->build_year]['price'] = (int) $row->average_price;
            }
        }

        return $data;
    }
}
