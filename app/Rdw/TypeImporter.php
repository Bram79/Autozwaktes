<?php

namespace App\Rdw;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;

/**
 * Fills `vehicle_types`: per car model, how many cars of each RDW type code were registered per year.
 * The type code (like "6R" for a Polo) changes when a new generation is built, which is what we use to find generations.
 */
class TypeImporter
{
    public function __construct(private RdwClient $rdw, private ModelNormalizer $normalizer) {}

    /** @return int number of rows saved */
    public function import(): int
    {
        $counts = [];   // [car_model_id][type][year] => vehicles

        foreach (Brand::pluck('rdw_name') as $rdwBrand) {
            foreach ($this->rdw->typeCounts($rdwBrand) as $row) {
                $modelId = $this->normalizer->match($rdwBrand, $row['handelsbenaming'] ?? '');
                $year = (int) ($row['jaar'] ?? 0);

                if ($modelId === null || $year < 1900) {
                    continue;
                }

                $type = self::cleanType($row['type'] ?? '');
                $counts[$modelId][$type][$year] = ($counts[$modelId][$type][$year] ?? 0) + (int) $row['n'];
            }
        }

        $rows = [];
        foreach ($counts as $modelId => $types) {
            foreach ($types as $type => $years) {
                foreach ($years as $year => $vehicles) {
                    $rows[] = ['car_model_id' => $modelId, 'type' => (string) $type, 'build_year' => $year, 'vehicles' => $vehicles];
                }
            }
        }

        DB::transaction(function () use ($rows) {
            DB::table('vehicle_types')->delete();

            foreach (array_chunk($rows, 2000) as $chunk) {
                DB::table('vehicle_types')->insert($chunk);
            }
        });

        return count($rows);
    }

    /** "6 R" => "6R"; the RDW's "-" (no type) => "". */
    public static function cleanType(string $type): string
    {
        $type = strtoupper(preg_replace('/\s+/', '', $type));

        return $type === '-' ? '' : mb_substr($type, 0, 40);
    }
}
