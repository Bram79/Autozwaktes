<?php

namespace App\Rdw;

use App\Models\Brand;
use Illuminate\Support\Str;

/** Finds the biggest passenger car brands in the RDW data and adds the ones we do not have yet. */
class BrandAdder
{
    /** Names the automatic spelling gets wrong. */
    private const NAMES = [
        'CITROEN' => 'Citroën',
        'SKODA' => 'Škoda',
        'BMW' => 'BMW',
        'MG' => 'MG',
        'DS' => 'DS',
        'LYNK&CO' => 'Lynk & Co',
        'SSANGYONG' => 'SsangYong',
        'DAIHATSU' => 'Daihatsu',
    ];

    public function __construct(private RdwClient $rdw) {}

    /**
     * The top brands with their status.
     *
     * @return array<int, array{rdw_name: string, name: string, cars: int, exists: bool}>
     */
    public function top(int $limit): array
    {
        $existing = Brand::pluck('rdw_name')->all();

        return array_map(fn ($row) => [
            'rdw_name' => $row['merk'],
            'name' => self::displayName($row['merk']),
            'cars' => (int) $row['n'],
            'exists' => in_array($row['merk'], $existing, true),
        ], array_values(array_filter($this->rdw->topBrands($limit), fn ($row) => filled($row['merk'] ?? null) && ($row['merk'] ?? '') !== '-')));
    }

    /** Add the brands that are new. Returns how many were added. */
    public function add(array $brands): int
    {
        $added = 0;

        foreach ($brands as $brand) {
            if ($brand['exists']) {
                continue;
            }

            $created = Brand::firstOrCreate(['rdw_name' => $brand['rdw_name']], ['name' => $brand['name'], 'slug' => Str::slug($brand['name'])]);
            $added += $created->wasRecentlyCreated ? 1 : 0;
        }

        return $added;
    }

    /** "MERCEDES-BENZ" => "Mercedes-Benz", "LAND ROVER" => "Land Rover", "CITROEN" => "Citroën" */
    public static function displayName(string $rdwName): string
    {
        if (isset(self::NAMES[$rdwName])) {
            return self::NAMES[$rdwName];
        }

        // two or three letters are almost always an abbreviation (BMW, MG)
        if (mb_strlen($rdwName) <= 3) {
            return $rdwName;
        }

        return implode('-', array_map(fn ($part) => implode(' ', array_map(fn ($word) => Str::ucfirst(Str::lower($word)), explode(' ', $part))), explode('-', $rdwName)));
    }
}
