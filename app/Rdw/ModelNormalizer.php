<?php

namespace App\Rdw;

use App\Models\Brand;
use App\Models\ModelAlias;
use Illuminate\Support\Str;

/**
 * Turns a messy RDW brand + "handelsbenaming" into one of our car models.
 *
 *   "AUDI" + "A4 AVANT"        -> A4
 *   "VOLKSWAGEN" + "861011 POLO" -> Polo
 *   "VOLKSWAGEN" + "ID. POLO"  -> null (not matched on purpose)
 */
class ModelNormalizer
{
    /** @var array<int, array<int, array{pattern: string, car_model_id: int}>>|null aliases per brand id */
    private ?array $aliases = null;

    /** @var array<string, int> brand rdw_name => brand id */
    private array $brands = [];

    public function match(string $brandRaw, string $modelRaw): ?int
    {
        $this->load();

        $brandId = $this->brands[Str::upper(trim($brandRaw))] ?? null;
        if ($brandId === null) {
            return null;
        }

        $name = $this->clean($modelRaw, Str::upper(trim($brandRaw)));

        foreach ($this->aliases[$brandId] ?? [] as $alias) {
            if (preg_match(self::regex($alias['pattern']), $name)) {
                return $alias['car_model_id'];
            }
        }

        return null;
    }

    /** Wrap an alias pattern in delimiters; a "/" in it is escaped unless it already is. */
    public static function regex(string $pattern): string
    {
        return '/'.preg_replace('#(?<!\\\\)/#', '\\/', $pattern).'/u';
    }

    /** Uppercase, collapse spaces, drop a leading brand name or numeric code. */
    public function clean(string $modelRaw, string $brandUpper): string
    {
        $name = Str::upper(trim(preg_replace('/\s+/', ' ', $modelRaw)));

        if (str_starts_with($name, $brandUpper.' ')) {
            $name = substr($name, strlen($brandUpper) + 1);
        }

        // "861011 POLO": a code of 5+ digits in front of the name
        return preg_replace('/^\d{5,} (?=\S)/', '', $name);
    }

    private function load(): void
    {
        if ($this->aliases !== null) {
            return;
        }

        $this->brands = Brand::pluck('id', 'rdw_name')->all();
        $this->aliases = [];

        $aliases = ModelAlias::with('carModel:id,brand_id')->orderBy('priority')->orderBy('id')->get();

        foreach ($aliases as $alias) {
            $this->aliases[$alias->carModel->brand_id][] = [
                'pattern' => $alias->pattern,
                'car_model_id' => $alias->car_model_id,
            ];
        }
    }
}
