<?php

namespace App\Site;

use App\Models\CarModel;
use App\Models\Generation;
use App\Rdw\ModelNormalizer;
use App\Rdw\RdwClient;
use App\Rdw\RdwUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Finds the page of a car by its license plate, using the RDW open data.
 * The plate itself is never stored; only the (public) car details are cached for a day.
 */
class PlateLookup
{
    public function __construct(private RdwClient $rdw, private ModelNormalizer $normalizer, private StatsService $stats) {}

    /** "ab-123-c" => "AB123C" */
    public static function normalize(string $input): string
    {
        return Str::upper(preg_replace('/[^A-Za-z0-9]/', '', $input));
    }

    /**
     * @return array{status: string, url?: string, car?: string}
     *                                                           status: invalid | not_found | not_supported | unavailable | unknown_model | no_stats | found
     */
    public function find(string $input): array
    {
        $plate = self::normalize($input);

        if (! preg_match('/^[A-Z0-9]{6}$/', $plate)) {
            return ['status' => 'invalid'];
        }

        try {
            // only plain arrays go into the cache; a plate that does not exist is cached as []
            $vehicle = Cache::remember('plate:'.sha1($plate), now()->addDay(), fn () => $this->rdw->findVehicle($plate) ?? []);
        } catch (RdwUnavailableException) {
            return ['status' => 'unavailable'];
        }

        if ($vehicle === []) {
            return ['status' => 'not_found'];
        }

        if (($vehicle['voertuigsoort'] ?? '') !== 'Personenauto') {
            return ['status' => 'not_supported'];
        }

        $modelId = $this->normalizer->match($vehicle['merk'] ?? '', $vehicle['handelsbenaming'] ?? '');
        $model = $modelId ? CarModel::with('brand')->find($modelId) : null;
        $year = (int) substr($vehicle['datum_eerste_toelating_dt'] ?? '', 0, 4);

        if (! $model || $year === 0) {
            return ['status' => 'unknown_model'];
        }

        $car = trim($model->brand->name.' '.$model->name.' '.$year);

        // the generation of that year that has statistics, with the year page if it exists
        $generation = $this->stats->generationsWithData($model)->first(fn (Generation $g) => $g->coversYear($year));

        if (! $generation) {
            return ['status' => 'no_stats', 'car' => $car, 'url' => route('model', [$model->brand, $model->slug])];
        }

        $url = isset($this->stats->years($generation)[$year])
            ? route('year', [$model->brand, $model->slug, $generation->slug, $year])
            : route('generation', [$model->brand, $model->slug, $generation->slug]);

        return ['status' => 'found', 'car' => $car, 'url' => $url];
    }
}
