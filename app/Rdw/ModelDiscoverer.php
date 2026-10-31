<?php

namespace App\Rdw;

use App\Models\Brand;
use App\Models\CarModel;
use App\Site\Seo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds car models in the imported RDW vehicles that we do not have yet, and creates them.
 *
 * A model is the first word(s) of the RDW name: "TRANSIT CONNECT" becomes "Transit".
 * That is a starting point; models and their aliases can be corrected in the admin.
 * Every new model gets one generation, "Alle bouwjaren", to be split up by hand later.
 */
class ModelDiscoverer
{
    /** First words that are not a model on their own: "GRAND C-MAX", "NEW BEETLE", "ID. BUZZ". */
    private const PREFIXES = ['GRAND', 'NEW', 'ALL', 'LAND', 'RANGE'];

    /** A build year only counts for the generation when this many vehicles have it (filters typos in the data). */
    private const MIN_VEHICLES_PER_YEAR = 10;

    public function __construct(private ModelNormalizer $normalizer) {}

    /**
     * Find the new models with at least $minVehicles vehicles.
     *
     * @return array<int, array{brand: Brand, name: string, slug: string, pattern: string, vehicles: int, year_from: int, year_to: int|null}>
     */
    public function discover(int $minVehicles): array
    {
        $brands = Brand::pluck('id', 'rdw_name');
        $groups = [];

        $rows = DB::table('staging_vehicles')
            ->selectRaw('brand_raw, model_raw, build_year, count(*) as vehicles')
            ->groupBy('brand_raw', 'model_raw', 'build_year')
            ->get();

        foreach ($rows as $row) {
            $brandUpper = Str::upper(trim($row->brand_raw));

            // already one of our models: nothing to discover
            if (! isset($brands[$brandUpper]) || $this->normalizer->match($row->brand_raw, $row->model_raw) !== null) {
                continue;
            }

            $token = $this->token($this->normalizer->clean($row->model_raw, $brandUpper));
            $slug = Str::slug($token ?? '');
            if ($slug === '') {
                continue;
            }

            $key = $brandUpper.'|'.$slug;
            $groups[$key] ??= ['brand_id' => $brands[$brandUpper], 'slug' => $slug, 'tokens' => [], 'years' => [], 'vehicles' => 0];
            $groups[$key]['tokens'][$token] = ($groups[$key]['tokens'][$token] ?? 0) + $row->vehicles;
            $groups[$key]['vehicles'] += $row->vehicles;
            if ($row->build_year !== null) {
                $groups[$key]['years'][$row->build_year] = ($groups[$key]['years'][$row->build_year] ?? 0) + $row->vehicles;
            }
        }

        $found = [];
        foreach ($groups as $group) {
            if ($group['vehicles'] < $minVehicles) {
                continue;
            }

            arsort($group['tokens']);
            $years = array_keys(array_filter($group['years'], fn ($n) => $n >= self::MIN_VEHICLES_PER_YEAR));
            if ($years === []) {
                continue;
            }

            $found[] = [
                'brand' => Brand::find($group['brand_id']),
                'name' => $this->displayName(array_key_first($group['tokens'])),
                'slug' => $group['slug'],
                'pattern' => '^(?:'.implode('|', array_map(fn ($t) => preg_quote($t, '/'), array_keys($group['tokens']))).')( |$)',
                'vehicles' => $group['vehicles'],
                'year_from' => min($years),
                // still being built: leave the end open so new years are included
                'year_to' => max($years) >= (int) date('Y') - 1 ? null : max($years),
            ];
        }

        usort($found, fn ($a, $b) => $b['vehicles'] <=> $a['vehicles']);

        return $found;
    }

    /** Create the models, aliases and the one generation per model. Returns how many models were created. */
    public function create(array $found): int
    {
        $created = 0;

        foreach ($found as $item) {
            $model = CarModel::firstOrCreate(
                ['brand_id' => $item['brand']->id, 'slug' => $item['slug']],
                ['name' => $item['name']],
            );
            $created += $model->wasRecentlyCreated ? 1 : 0;

            $model->aliases()->firstOrCreate(['pattern' => $item['pattern']], ['priority' => 100]);

            // a model that was made by hand already has its own generations
            if (! $model->generations()->exists()) {
                $model->generations()->create([
                    'code' => Seo::ALL_YEARS,
                    'slug' => 'alle-bouwjaren',
                    'year_from' => $item['year_from'],
                    'year_to' => $item['year_to'],
                ]);
            }
        }

        return $created;
    }

    /** "TRANSIT CONNECT 1.8" => "TRANSIT"; "GRAND C-MAX" => "GRAND C-MAX"; "CORSA-E" => "CORSA". */
    private function token(string $name): ?string
    {
        $words = explode(' ', $name);
        // "CORSA-E" and "MERIVA-A" are versions of Corsa and Meriva
        $first = preg_replace('/(?<=\w{3})-[A-F]$/', '', $words[0]);

        $needsSecond = in_array($first, self::PREFIXES, true) || str_ends_with($first, '.') || mb_strlen($first) === 1;

        return $needsSecond && isset($words[1]) ? $first.' '.$words[1] : $first;
    }

    /** "T-ROC" => "T-Roc", but "A4" and "ID.3" keep their capitals. */
    private function displayName(string $token): string
    {
        return collect(explode(' ', $token))
            ->map(fn ($word) => preg_match('/\d/', $word) ? $word : implode('-', array_map(Str::ucfirst(...), array_map(Str::lower(...), explode('-', $word)))))
            ->implode(' ');
    }
}
