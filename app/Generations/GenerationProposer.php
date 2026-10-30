<?php

namespace App\Generations;

use App\Models\CarModel;
use App\Wikidata\WikidataClient;
use Illuminate\Support\Facades\DB;

/**
 * Proposes generations (name + years) for one car model.
 *
 * 1. The RDW type code changes when a new generation is built ("9N", "6R", "AW" for the Polo).
 *    Per registration year the most common type "wins"; a run of years with the same winner is one generation.
 * 2. Wikidata gives the familiar names (Mk4, Mk5) and the years. A proposal that matches a Wikidata
 *    generation gets a higher confidence; where Wikidata knows more generations than the RDW codes, it splits.
 * 3. Old generations without RDW type codes (only registered before type codes were filled in) come from Wikidata alone.
 *
 * The result is only a proposal: a person approves it in the admin.
 */
class GenerationProposer
{
    /** A type code needs at least this share of all cars with a type code, or it is treated as noise. */
    private const MIN_SHARE = 0.02;

    public function __construct(private WikidataClient $wikidata) {}

    /**
     * @param  array<int, array{short: string, start: int, end: int|null}>  $wikidata  generations known by Wikidata
     * @return array<int, array{code: string, year_from: int, year_to: int|null, confidence: int, source: string, vehicles: int, notes: string}>
     */
    public function propose(CarModel $model, array $wikidata = []): array
    {
        $rows = DB::table('vehicle_types')->where('car_model_id', $model->id)->get();

        $perYear = [];          // all cars per year, with or without type code
        $typed = [];            // [type][year] => cars
        foreach ($rows as $row) {
            $perYear[$row->build_year] = ($perYear[$row->build_year] ?? 0) + $row->vehicles;
            if ($row->type !== '') {
                $typed[$row->type][$row->build_year] = ($typed[$row->type][$row->build_year] ?? 0) + $row->vehicles;
            }
        }

        $now = (int) date('Y');
        $minSample = (int) config('site.min_sample');
        $runs = $this->runs($this->clusters($typed));
        $proposals = [];

        foreach ($runs as $i => $run) {
            array_push($proposals, ...$this->matchWithWikidata($run, $wikidata, $now, $i === 0));
        }

        if ($runs !== []) {
            array_push($proposals, ...$this->olderGenerations($wikidata, $runs[0]['from'], $perYear, $minSample));
        }

        $proposals = $this->tidy($proposals, $perYear, $now, $minSample);

        // "Oudere bouwjaren" does not count: one real generation is not worth a proposal, the model already has "Alle bouwjaren"
        $real = array_filter($proposals, fn ($p) => $p['code'] !== 'Oudere bouwjaren');

        return count($real) >= 2 ? $proposals : [];
    }

    /**
     * Types that belong together become one cluster: "AU" and "AUV" are both Golf 7.
     *
     * @return array<string, array<int, int>> cluster name => [year => cars], only clusters that are not noise
     */
    private function clusters(array $typed): array
    {
        $totals = array_map('array_sum', $typed);
        arsort($totals);                                   // the biggest type names the cluster
        $clusters = [];

        foreach (array_keys($totals) as $type) {
            $parent = null;
            foreach (array_keys($clusters) as $name) {
                if (strlen($name) >= 2 && str_starts_with($type, $name)) {
                    $parent = $name;
                    break;
                }
            }

            foreach ($typed[$type] as $year => $cars) {
                $clusters[$parent ?? $type][$year] = ($clusters[$parent ?? $type][$year] ?? 0) + $cars;
            }
        }

        $all = array_sum($totals);
        $clusters = array_filter($clusters, fn ($years) => array_sum($years) >= max(50, $all * self::MIN_SHARE));

        // a few stray cars in an early or late year do not make that year part of the generation
        return array_map(fn ($years) => array_filter($years, fn ($cars) => $cars >= max(max($years) * 0.01, 3)), $clusters);
    }

    /**
     * Runs of years where the same cluster is the most common one.
     *
     * @return array<int, array{cluster: string, from: int, to: int, cars: int}>
     */
    private function runs(array $clusters): array
    {
        $years = array_unique(array_merge([], ...array_values(array_map('array_keys', $clusters))));
        sort($years);
        $runs = [];

        // the year a cluster first shows up: a generation never comes back after a newer one took over, and a type that is not newer than the current one does not start a new generation
        $firstYear = array_map(fn ($perYear) => min(array_keys($perYear)), $clusters);

        foreach ($years as $year) {
            $winner = null;
            $best = 0;
            foreach ($clusters as $name => $perYear) {
                if (($perYear[$year] ?? 0) > $best) {
                    $best = $perYear[$year];
                    $winner = $name;
                }
            }

            $last = array_key_last($runs);
            if ($last !== null && $winner !== $runs[$last]['cluster'] && $firstYear[$winner] <= $firstYear[$runs[$last]['cluster']]) {
                $winner = $runs[$last]['cluster'];
            }

            if ($last !== null && $runs[$last]['cluster'] === $winner) {
                $runs[$last]['to'] = $year;
            } else {
                $runs[] = ['cluster' => $winner, 'from' => $year, 'to' => $year, 'cars' => 0];
            }
        }

        foreach ($runs as &$run) {
            $run['cars'] = array_sum(array_filter($clusters[$run['cluster']], fn ($y) => $y >= $run['from'] && $y <= $run['to'], ARRAY_FILTER_USE_KEY));
        }
        unset($run);

        // a run of a few cars is a blip between two real generations: leave it out and let the one before it cover it
        $all = array_sum(array_column($runs, 'cars'));
        $runs = array_values(array_filter($runs, fn ($run) => $run['cars'] >= $all * self::MIN_SHARE));

        // two runs of the same cluster next to each other become one
        $merged = [];
        foreach ($runs as $run) {
            $last = array_key_last($merged);
            if ($last !== null && $merged[$last]['cluster'] === $run['cluster']) {
                $merged[$last]['to'] = $run['to'];
                $merged[$last]['cars'] += $run['cars'];
            } else {
                $merged[] = $run;
            }
        }

        return $merged;
    }

    /**
     * Give one run its name and confidence. A run that contains the start of more Wikidata generations is split.
     *
     * @return array<int, array<string, mixed>>
     */
    private function matchWithWikidata(array $run, array $wikidata, int $now, bool $isFirstRun): array
    {
        $base = $run['cars'] >= 5000 ? 65 : ($run['cars'] >= 1000 ? 55 : 45);

        // some type codes are cut off by the RDW ("2*****") or carry a market note ("XA5(EU,M)"): show them clean, trust them less
        $cluster = $run['cluster'];
        $label = trim(preg_replace('/\(.*$/', '', rtrim($cluster, '*')) ?: $cluster, '*');
        $run['cluster'] = $label;
        $base = str_contains($cluster, '*') ? max(15, $base - 25) : $base;

        // boundaries inside this run where Wikidata says a new generation starts
        $starts = [$run['from']];
        foreach ($wikidata as $generation) {
            if ($generation['start'] > $run['from'] + 1 && $generation['start'] <= $run['to'] && $generation['start'] - end($starts) >= 2) {
                $starts[] = $generation['start'];
            }
        }

        $pieces = [];
        foreach ($starts as $i => $from) {
            $to = isset($starts[$i + 1]) ? $starts[$i + 1] - 1 : $run['to'];
            $match = $this->findByStart($wikidata, $from);
            // the RDW type codes only start halfway a generation: Wikidata knows it began earlier
            $containing = $isFirstRun && $i === 0 && ! $match ? $this->findContaining($wikidata, $from) : null;

            $piece = ['year_from' => $from, 'year_to' => $to, 'vehicles' => 0, 'wd_short' => ($match ?? $containing)['short'] ?? null];

            if (count($starts) > 1) {
                // same RDW type, so only Wikidata tells where the border is
                $piece += [
                    'code' => $match['short'] ?? $run['cluster'],
                    'confidence' => $match ? 70 : 50,
                    'source' => $match ? 'rdw+wikidata' : 'rdw',
                    'notes' => "RDW-type {$run['cluster']} loopt hier door; de grens komt van Wikidata.",
                ];
            } elseif ($match) {
                $endAgrees = $match['end'] === null ? $run['to'] >= $now - 1 : abs($match['end'] - $run['to']) <= 1;
                $piece += [
                    'code' => $match['short'] === $run['cluster'] ? $run['cluster'] : "{$match['short']} ({$run['cluster']})",
                    'confidence' => min(98, $base + ($endAgrees ? 30 : 20)),
                    'source' => 'rdw+wikidata',
                    'notes' => $endAgrees ? 'Begin- en eindjaar kloppen met Wikidata.' : 'Beginjaar klopt met Wikidata, eindjaar wijkt af.',
                ];
            } elseif ($containing) {
                $piece += [
                    'code' => "{$containing['short']} ({$run['cluster']})",
                    'confidence' => $base + 10,
                    'source' => 'rdw+wikidata',
                    'notes' => 'De RDW-typecode begint pas later dan deze generatie; het begin komt van Wikidata.',
                ];
            } else {
                $piece += ['code' => $run['cluster'], 'confidence' => $base, 'source' => 'rdw', 'notes' => 'Alleen op RDW-typecode gebaseerd.'];
            }

            $pieces[] = $piece;
        }

        return $pieces;
    }

    /** Wikidata generations that ended before the first RDW type code shows up (old cars have no type code). */
    private function olderGenerations(array $wikidata, int $firstYear, array $perYear, int $minSample): array
    {
        $older = [];

        foreach ($wikidata as $i => $generation) {
            if ($generation['start'] >= $firstYear - 1) {
                continue;
            }

            $end = $generation['end'] ?? (($wikidata[$i + 1]['start'] ?? $firstYear) - 1);
            $to = min($end, $firstYear - 1);
            $cars = array_sum(array_filter($perYear, fn ($y) => $y >= $generation['start'] && $y <= $to, ARRAY_FILTER_USE_KEY));

            if ($to >= $generation['start'] && $cars >= $minSample) {
                $older[] = [
                    'code' => $generation['short'], 'wd_short' => $generation['short'], 'year_from' => $generation['start'], 'year_to' => $to,
                    'confidence' => 45, 'source' => 'wikidata', 'vehicles' => 0,
                    'notes' => 'Alleen Wikidata: oude auto\'s hebben geen RDW-typecode om mee te controleren.',
                ];
            }
        }

        return $older;
    }

    /** Sort, merge the same generation, close gaps, open the last generation, and count the cars. */
    private function tidy(array $proposals, array $perYear, int $now, int $minSample): array
    {
        usort($proposals, fn ($a, $b) => $a['year_from'] <=> $b['year_from']);

        // the same Wikidata generation twice in a row (the RDW type changed halfway) becomes one
        $merged = [];
        foreach ($proposals as $proposal) {
            $last = array_key_last($merged);
            if ($last !== null && $proposal['wd_short'] !== null && $merged[$last]['wd_short'] === $proposal['wd_short']) {
                $merged[$last]['year_to'] = $proposal['year_to'];
                $merged[$last]['confidence'] = min($merged[$last]['confidence'], $proposal['confidence']);
                $merged[$last]['code'] = strlen($proposal['code']) > strlen($merged[$last]['code']) ? $proposal['code'] : $merged[$last]['code'];
            } else {
                $merged[] = $proposal;
            }
        }
        $proposals = $merged;

        // cars from before the first generation (no RDW type code yet) must not be dropped
        if ($proposals !== []) {
            $first = $proposals[0]['year_from'];
            $earlyYears = array_filter(array_keys($perYear), fn ($y) => $y < $first && $perYear[$y] >= 5);
            $earlyCars = array_sum(array_intersect_key($perYear, array_flip($earlyYears)));

            if ($earlyYears !== [] && $earlyCars >= $minSample) {
                array_unshift($proposals, [
                    'code' => 'Oudere bouwjaren', 'year_from' => min($earlyYears), 'year_to' => $first - 1,
                    'confidence' => 35, 'source' => 'rdw', 'vehicles' => 0, 'wd_short' => null,
                    'notes' => 'Auto\'s van voor de RDW-typecodes. Splits dit zelf op in generaties.',
                ]);
            }
        }

        $count = count($proposals);
        foreach ($proposals as $i => &$proposal) {
            if ($i + 1 < $count) {
                $proposal['year_to'] = $proposals[$i + 1]['year_from'] - 1;
            } elseif (($proposal['year_to'] ?? 0) >= $now - 1) {
                $proposal['year_to'] = null;
            }

            $end = $proposal['year_to'] ?? $now;
            $proposal['vehicles'] = array_sum(array_filter($perYear, fn ($y) => $y >= $proposal['year_from'] && $y <= $end, ARRAY_FILTER_USE_KEY));
            unset($proposal['wd_short']);
        }
        unset($proposal);

        return array_values(array_filter($proposals, fn ($p) => $p['year_to'] === null || $p['year_to'] >= $p['year_from']));
    }

    /** The Wikidata generation that was already running in $year (it started more than a year before). */
    private function findContaining(array $wikidata, int $year): ?array
    {
        foreach ($wikidata as $i => $generation) {
            $end = $generation['end'] ?? (($wikidata[$i + 1]['start'] ?? PHP_INT_MAX) - 1);

            if ($generation['start'] < $year - 1 && $end >= $year) {
                return $generation;
            }
        }

        return null;
    }

    private function findByStart(array $wikidata, int $year): ?array
    {
        foreach ($wikidata as $generation) {
            if (abs($generation['start'] - $year) <= 1) {
                return $generation;
            }
        }

        return null;
    }
}
