<?php

namespace Database\Seeders;

use App\Models\DefectCode;
use App\Models\Generation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * FAKE numbers for local development, so the pages can be viewed before the real
 * RDW import has run. Never run this in production: php artisan db:seed --class=DemoStatsSeeder
 */
class DemoStatsSeeder extends Seeder
{
    public function run(): void
    {
        $codes = [
            '101' => 'Remschijf of remtrommel versleten',
            '102' => 'Stuurkogel of fusee speling',
            '103' => 'Schokdemper lekt',
            '104' => 'Uitlaat lekt',
            '105' => 'Verlichting afstelling afwijkend',
            '106' => 'Bandenprofiel te laag',
            '107' => 'Ruitenwisser versleten',
            '108' => 'Wiellager speling',
            '109' => 'Draagarmrubber gescheurd',
            '110' => 'Remleiding poreus of beschadigd',
            '111' => 'Koplamp defect',
        ];
        foreach ($codes as $code => $description) {
            DefectCode::updateOrCreate(['code' => $code], ['description' => $description]);
        }

        DB::table('defect_stats')->delete();
        DB::table('vehicle_counts')->delete();

        mt_srand(42);
        foreach (Generation::all() as $generation) {
            $to = min($generation->year_to ?? 2022, 2022);
            for ($year = $generation->year_from; $year <= $to; $year++) {
                foreach (['Benzine' => 0.7, 'Diesel' => 0.3] as $fuel => $share) {
                    $total = (int) (mt_rand(800, 6000) * $share);
                    DB::table('vehicle_counts')->insert(['car_model_id' => $generation->car_model_id, 'build_year' => $year, 'fuel' => $fuel, 'total_vehicles' => $total]);

                    foreach (array_keys($codes) as $code) {
                        $with = (int) ($total * mt_rand(2, 35) / 100);
                        DB::table('defect_stats')->insert([
                            'car_model_id' => $generation->car_model_id, 'generation_id' => $generation->id,
                            'build_year' => $year, 'fuel' => $fuel, 'defect_code' => $code,
                            'vehicles_with_defect' => $with, 'total_vehicles' => $total,
                            'percentage' => round($with / max($total, 1) * 100, 2),
                        ]);
                    }
                }
            }
        }
    }
}
