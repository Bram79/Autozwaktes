<?php

use App\Models\CarModel;
use App\Models\DefectStat;
use App\Models\VehicleCount;
use App\Rdw\StatisticsCalculator;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

function vehicle(string $plate, string $model, int $year, ?string $fuel = 'Benzine', ?string $fuel2 = null, string $brand = 'AUDI'): array
{
    return ['license_plate' => $plate, 'brand_raw' => $brand, 'model_raw' => $model, 'build_year' => $year, 'fuel' => $fuel, 'fuel_2' => $fuel2];
}

function defect(string $plate, string $code, string $at = '2024-03-01 10:00:00'): array
{
    return ['license_plate' => $plate, 'defect_code' => $code, 'reported_at' => $at, 'defect_count' => 1];
}

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);

    DB::table('staging_vehicles')->insert([
        vehicle('AA1', 'A4 AVANT', 2010),
        vehicle('AA2', 'A4 LIMOUSINE', 2010),
        vehicle('AA3', 'A4', 2010),
        vehicle('AA4', 'A4', 2010),
        vehicle('AA5', 'A4', 2010, 'Diesel'),
        vehicle('AA6', 'A4', 2010, 'Benzine', 'Elektriciteit'),   // hybrid
        vehicle('BB1', 'ID. POLO', 2024, 'Elektriciteit', null, 'VOLKSWAGEN'), // no match
    ]);

    DB::table('staging_defects')->insert([
        defect('AA1', '576'),
        defect('AA1', '576', '2025-03-01 10:00:00'),  // same car, second inspection: counts once
        defect('AA2', '576'),
        defect('AA1', '123'),
        defect('AA5', '576'),
        defect('BB1', '576'),
    ]);
});

it('calculates vehicle counts and percentages per model, year and fuel', function () {
    app(StatisticsCalculator::class)->run();

    $a4 = CarModel::where('slug', 'a4')->value('id');

    expect(VehicleCount::where('car_model_id', $a4)->where('fuel', 'Benzine')->value('total_vehicles'))->toBe(4);
    expect(VehicleCount::where('car_model_id', $a4)->where('fuel', 'Diesel')->value('total_vehicles'))->toBe(1);
    expect(VehicleCount::where('car_model_id', $a4)->where('fuel', 'Hybride')->value('total_vehicles'))->toBe(1);

    $stat = DefectStat::where('car_model_id', $a4)->where('fuel', 'Benzine')->where('defect_code', '576')->first();
    expect($stat->vehicles_with_defect)->toBe(2)   // AA1 and AA2, AA1 counted once
        ->and($stat->total_vehicles)->toBe(4)
        ->and((float) $stat->percentage)->toBe(50.0)
        ->and($stat->generation->code)->toBe('B8');

    $other = DefectStat::where('car_model_id', $a4)->where('fuel', 'Benzine')->where('defect_code', '123')->first();
    expect((float) $other->percentage)->toBe(25.0);
});

it('ignores vehicles that match none of our models', function () {
    app(StatisticsCalculator::class)->run();

    expect(VehicleCount::count())->toBe(3);   // A4 Benzine, Diesel, Hybride
});

it('gives the same result when run twice and clears the cache', function () {
    Cache::put('page', 'old');

    app(StatisticsCalculator::class)->run();
    $first = DefectStat::count();
    app(StatisticsCalculator::class)->run();

    expect(DefectStat::count())->toBe($first)->and($first)->toBeGreaterThan(0);
    expect(Cache::get('page'))->toBeNull();
});
