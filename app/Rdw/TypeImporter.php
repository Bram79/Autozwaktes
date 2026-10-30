<?php

namespace App\Rdw;

use App\Models\Brand;
use Illuminate\Support\Facades\DB;

/**
 * Fills `vehicle_types`: per car model, how many cars of each RDW type code were registered per year.
 * The type code (like "6R" for a Polo) changes when a new generation is built, which is what we use to find generations.
 * Also fills `model_prices` with the average catalogue price (new price) per model and year.
 */
class TypeImporter
{
    public function __construct(private RdwClient $rdw, private ModelNormalizer $normalizer) {}

    /** @return int number of rows saved */
    public function import(): int
    {
        $counts = [];   // [car_model_id][type][year] => vehicles
        $prices = [];   // [car_model_id][year] => [cars with a price, sum of their prices]

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

        // (second loop over the brands: the price numbers are a different question to the RDW)
        foreach (Brand::pluck('rdw_name') as $rdwBrand) {
            foreach ($this->rdw->priceAverages($rdwBrand) as $row) {
                $modelId = $this->normalizer->match($rdwBrand, $row['handelsbenaming'] ?? '');
                $year = (int) ($row['jaar'] ?? 0);

                if ($modelId === null || $year < 1900) {
                    continue;
                }

                $cars = (int) $row['n'];
                $prices[$modelId][$year] = [($prices[$modelId][$year][0] ?? 0) + $cars, ($prices[$modelId][$year][1] ?? 0) + $cars * (float) $row['prijs']];
            }
        }

        $priceRows = [];
        foreach ($prices as $modelId => $years) {
            foreach ($years as $year => [$cars, $sum]) {
                $priceRows[] = ['car_model_id' => $modelId, 'build_year' => $year, 'vehicles' => $cars, 'average_price' => (int) round($sum / $cars)];
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

        DB::transaction(function () use ($rows, $priceRows) {
            DB::table('vehicle_types')->delete();
            DB::table('model_prices')->delete();

            foreach (array_chunk($rows, 2000) as $chunk) {
                DB::table('vehicle_types')->insert($chunk);
            }
            foreach (array_chunk($priceRows, 2000) as $chunk) {
                DB::table('model_prices')->insert($chunk);
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
