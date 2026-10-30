<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Generation;
use App\Site\ComparisonService;
use App\Site\RankingService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-31');   // a car gets its first APK after 4 years: 2022 is the last build year that has had one
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

/** Cars and defects for one model, one build year and one fuel; $rate defects per 100 cars. */
function fleetOf(CarModel $model, int $year, int $cars, float $rate, string $fuel = 'Benzine'): void
{
    DB::table('vehicle_counts')->updateOrInsert(
        ['car_model_id' => $model->id, 'build_year' => $year, 'fuel' => $fuel],
        ['total_vehicles' => $cars],
    );

    $generation = $model->generations()->get()->first(fn (Generation $g) => $g->coversYear($year));
    DB::table('defect_stats')->updateOrInsert(
        ['car_model_id' => $model->id, 'build_year' => $year, 'fuel' => $fuel, 'defect_code' => '999'],
        ['generation_id' => $generation?->id, 'vehicles_with_defect' => (int) round($cars * $rate / 100), 'total_vehicles' => $cars, 'percentage' => $rate],
    );
}

function newModel(string $name, int $from, ?int $to = null): CarModel
{
    $brand = Brand::first();
    $model = $brand->carModels()->create(['name' => $name, 'slug' => str($name)->slug()]);
    $model->generations()->create(['code' => 'G1', 'slug' => 'g1', 'year_from' => $from, 'year_to' => $to]);

    return $model;
}

it('knows which build years have had an APK', function () {
    expect(RankingService::lastInspectedYear())->toBe(2022);
});

it('leaves build years without APK out of the ranking years and the ranking page', function () {
    DB::table('vehicle_counts')->insert(['car_model_id' => CarModel::where('slug', 'polo')->value('id'), 'build_year' => 2024, 'fuel' => 'Benzine', 'total_vehicles' => 50000]);
    Cache::flush();

    $years = app(RankingService::class)->years();

    expect($years)->not->toContain(2024)->not->toContain(2023)->toContain(2022);
    $this->get('/ranglijst/bouwjaar/2024')->assertNotFound();
    $this->get('/ranglijst/bouwjaar/2022')->assertOk();
});

it('does not rank a generation that only has cars that never had an APK', function () {
    $newcomer = newModel('Newcomer', 2023);
    fleetOf($newcomer, 2023, 8000, 0.0);
    fleetOf($newcomer, 2024, 9000, 0.1);   // looks like the best car there is, but nobody has inspected it yet
    Cache::flush();

    $names = collect(app(RankingService::class)->scores())->pluck('model');

    expect($names)->not->toContain('Newcomer');
    $this->get('/ranglijst/alles')->assertDontSee('Newcomer');
});

it('only counts the build years that had an APK for a generation that has both', function () {
    $mixed = newModel('Mixed', 2020);
    fleetOf($mixed, 2020, 1000, 150);
    fleetOf($mixed, 2021, 1000, 150);
    fleetOf($mixed, 2024, 20000, 0.0);   // must not water the score down
    Cache::flush();

    $row = collect(app(RankingService::class)->scores())->firstWhere('model', 'Mixed');

    expect($row['total'])->toBe(2000)->and($row['score'])->toBe(150.0);
});

it('ranks on the score compared with the average of cars of the same build years, where 100 is average', function () {
    // two models in the same years: A is 20% below the average of the two, B 20% above
    DB::table('defect_stats')->delete();
    DB::table('vehicle_counts')->delete();
    $a = newModel('Alpha', 2010, 2012);
    $b = newModel('Bravo', 2010, 2012);
    foreach ([2010, 2011, 2012] as $year) {
        fleetOf($a, $year, 1000, 80);
        fleetOf($b, $year, 1000, 120);
    }
    Cache::flush();

    $rows = collect(app(RankingService::class)->scores())->keyBy('model');

    expect($rows['Alpha']['index'])->toBe(80.0)->and($rows['Bravo']['index'])->toBe(120.0);
    expect($rows['Alpha']['score'])->toBe(80.0);   // the absolute number stays available
    expect(array_column(app(RankingService::class)->scores(), 'model'))->toBe(['Alpha', 'Bravo']);
});

it('does not punish an old car for being old: the same rate in other years gives the same index', function () {
    // old cars have far more defects than young ones, but each is compared with its own build years
    DB::table('defect_stats')->delete();
    DB::table('vehicle_counts')->delete();
    $old = newModel('Oldie', 2008, 2010);
    $young = newModel('Youngie', 2018, 2020);
    $oldPeer = newModel('OldPeer', 2008, 2010);
    $youngPeer = newModel('YoungPeer', 2018, 2020);
    foreach ([2008, 2009, 2010] as $year) {
        fleetOf($old, $year, 1000, 300 * 0.9);
        fleetOf($oldPeer, $year, 1000, 300 * 1.1);
    }
    foreach ([2018, 2019, 2020] as $year) {
        fleetOf($young, $year, 1000, 30 * 0.9);
        fleetOf($youngPeer, $year, 1000, 30 * 1.1);
    }
    Cache::flush();

    $rows = collect(app(RankingService::class)->scores())->keyBy('model');

    expect($rows['Oldie']['index'])->toBe($rows['Youngie']['index'])->toBe(90.0);
    expect($rows['Oldie']['score'])->toBeGreaterThan($rows['Youngie']['score'] * 5);
});

it('explains on the ranking page what 100 means and why new cars are missing', function () {
    $this->get('/ranglijst/alles')->assertOk()
        ->assertSee('100 = gemiddeld', false)
        ->assertSee('bouwjaar 2022', false)
        ->assertSee('nog geen APK', false);
});

it('leaves years without APK out of a comparison', function () {
    DB::table('model_prices')->delete();
    $a = CarModel::where('slug', 'polo')->first();
    $b = CarModel::where('slug', 'corsa')->first();
    foreach ([$a, $b] as $model) {
        foreach (range(2018, 2024) as $year) {
            fleetOf($model, $year, 5000, 10);
            DB::table('model_prices')->updateOrInsert(['car_model_id' => $model->id, 'build_year' => $year], ['vehicles' => 500, 'average_price' => 20000]);
        }
    }
    Cache::flush();

    $result = app(ComparisonService::class)->compare($a, $b);

    expect($result['years'])->toContain(2022)->not->toContain(2023)->not->toContain(2024);
});

it('warns on the page of a build year that has not had an APK yet', function () {
    $polo = CarModel::where('slug', 'polo')->first();
    fleetOf($polo, 2024, 5000, 0.2);
    fleetOf($polo, 2020, 5000, 20);
    Cache::flush();
    $generation = $polo->generations()->get()->first(fn ($g) => $g->coversYear(2024));

    $this->get("/volkswagen/polo/{$generation->slug}/2024")->assertOk()->assertSee('nog geen APK', false);
    $this->get("/volkswagen/polo/{$generation->slug}/2020")->assertOk()->assertDontSee('nog geen APK', false);
});

it('leaves very old cars out of the ranking, because only the best kept ones survive', function () {
    $classic = newModel('Classic', 1990, 1999);
    fleetOf($classic, 1995, 5000, 5);   // looks excellent, but these are the few that were looked after for 30 years
    $old = newModel('Ancient', 2000, 2008);
    fleetOf($old, 2000, 3000, 5);
    fleetOf($old, 2007, 3000, 90);
    Cache::flush();

    expect(RankingService::firstRankedYear())->toBe(2006);

    $rows = collect(app(RankingService::class)->scores());
    expect($rows->pluck('model'))->not->toContain('Classic');
    expect($rows->firstWhere('model', 'Ancient')['total'])->toBe(3000);   // only 2007 counts, 2000 is too old
    expect(app(RankingService::class)->years())->not->toContain(2005)->not->toContain(1995);
    $this->get('/ranglijst/bouwjaar/2005')->assertNotFound();
    $this->get('/ranglijst/alles')->assertSee('tot en met bouwjaar 2022', false)->assertSee('bouwjaar 2006', false);
});
