<?php

use App\Models\CarModel;
use App\Models\Generation;
use App\Site\ComparisonService;
use App\Site\RankingService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

/** Give every model with cars a new price: 20.000 euro, except the Audi (45.000). */
function givePrices(): void
{
    $audi = CarModel::where('slug', 'a4')->value('id');

    foreach (DB::table('vehicle_counts')->select('car_model_id', 'build_year')->distinct()->get() as $row) {
        DB::table('model_prices')->insert([
            'car_model_id' => $row->car_model_id, 'build_year' => $row->build_year, 'vehicles' => 500, 'average_price' => $row->car_model_id === $audi ? 45000 : 20000,
        ]);
    }
    Cache::flush();
}

// --- rankings ---------------------------------------------------------------------------------------------------

it('ranks generations by defects per 100 cars, lowest first, and the score matches the database', function () {
    $rows = app(RankingService::class)->scores();

    expect(count($rows))->toBeGreaterThan(5);
    expect(array_column($rows, 'score'))->toBe(collect(array_column($rows, 'score'))->sort()->values()->all());

    $first = $rows[0];
    $generation = Generation::whereHas('carModel', fn ($q) => $q->where('slug', $first['model_slug']))->where('slug', $first['generation_slug'])->first();
    $defects = DB::table('defect_stats')->where('generation_id', $generation->id)->sum('vehicles_with_defect');
    $cars = DB::table('vehicle_counts')->where('car_model_id', $generation->car_model_id)
        ->whereBetween('build_year', [$generation->year_from, $generation->year_to ?? 9999])->sum('total_vehicles');

    expect($first['total'])->toBe((int) $cars);
    expect($first['score'])->toBe(round($defects / $cars * 100, 1));
});

it('leaves out generations with too few cars', function () {
    $before = count(app(RankingService::class)->scores());
    config(['site.min_ranking_sample' => 20000]);
    Cache::flush();

    expect(count(app(RankingService::class)->scores()))->toBeLessThan($before);
});

it('filters a ranking on brand, fuel and build year', function () {
    $service = app(RankingService::class);
    $audi = \App\Models\Brand::where('slug', 'audi')->first();

    expect(collect($service->scores($audi->id))->pluck('brand')->unique()->all())->toBe(['Audi']);
    expect($service->fuels())->toHaveKey('benzine');
    expect($service->years())->not->toBeEmpty();

    $year = $service->years()[0];
    expect($service->scores(null, 'Benzine', $year))->not->toBe($service->scores());
});

it('shows ranking pages with a unique title, and noindex when the list is short', function () {
    $this->get('/ranglijsten')->assertOk()->assertSee('Ranglijsten');
    $this->get('/ranglijst/alles')->assertOk()->assertSee('De minste gebreken')->assertSee('De meeste gebreken')
        ->assertDontSee('noindex', false);   // long enough: indexed

    // Kia only has the Picanto: fewer than 5 rows is too short to be worth a place in Google
    $this->get('/ranglijst/merk/kia')->assertOk()->assertSee('Minste APK-gebreken bij Kia')->assertSee('noindex,follow', false);

    $this->get('/ranglijst/brandstof/benzine')->assertOk()->assertSee('minste APK-gebreken');
    $this->get('/ranglijst/bouwjaar/'.app(RankingService::class)->years()[0])->assertOk()->assertSee('Betrouwbaarste occasions');
});

it('gives 404 for rankings that do not exist', function () {
    $this->get('/ranglijst/merk/bestaatniet')->assertNotFound();
    $this->get('/ranglijst/brandstof/stoom')->assertNotFound();
    $this->get('/ranglijst/bouwjaar/1850')->assertNotFound();
    $this->get('/ranglijst/onzin')->assertNotFound();
    $this->get('/ranglijst/alles/extra')->assertNotFound();
});

it('lists only the rankings with enough rows in the sitemap', function () {
    $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($sitemap)->toContain('/ranglijsten')->toContain('/ranglijst/alles')->not->toContain('/ranglijst/merk/kia');
});

// --- comparisons -------------------------------------------------------------------------------------------------

it('only makes pairs of models that were sold side by side and cost about the same when new', function () {
    givePrices();
    $pairs = app(ComparisonService::class)->pairs();
    $flat = collect($pairs)->flatten();

    expect($pairs)->not->toBeEmpty();
    expect($flat)->not->toContain('audi-a4');   // 45.000 against 20.000: not the same segment
    foreach ($pairs as [$a, $b]) {
        expect($a < $b)->toBeTrue();            // one canonical order per pair
    }
    expect(count($pairs))->toBeLessThanOrEqual(count(array_unique($flat->all())) * 3);
});

it('makes no pairs at all without prices', function () {
    expect(app(ComparisonService::class)->pairs())->toBe([]);
    $this->get('/vergelijk/opel-corsa-vs-volkswagen-polo')->assertNotFound();
});

it('shows a comparison page with numbers that match the database', function () {
    givePrices();
    [$a, $b] = app(ComparisonService::class)->pairs()[0];
    $slug = ComparisonService::slug($a, $b);

    $response = $this->get("/vergelijk/{$slug}")->assertOk();

    $models = app(ComparisonService::class)->find($slug);
    $result = app(ComparisonService::class)->compare(...$models);

    // recalculate the first model's score over the shared years straight from the tables
    $years = $result['years'];
    $cars = DB::table('vehicle_counts')->where('car_model_id', $models[0]->id)->whereIn('build_year', $years)->sum('total_vehicles');
    $defects = DB::table('defect_stats')->where('car_model_id', $models[0]->id)->whereIn('build_year', $years)->sum('vehicles_with_defect');

    expect($result['models'][$models[0]->id])->toBe(['total' => (int) $cars, 'score' => round($defects / $cars * 100, 1)]);
    $response->assertSee(number_format($result['models'][$models[0]->id]['score'], 1, ',', '.'))
        ->assertSee($models[0]->brand->name.' '.$models[0]->name.' vs '.$models[1]->brand->name.' '.$models[1]->name)
        ->assertSee('De meest voorkomende gebreken naast elkaar');
});

it('gives 404 for a pair in the wrong order or without a page', function () {
    givePrices();
    [$a, $b] = app(ComparisonService::class)->pairs()[0];

    $this->get("/vergelijk/{$b}-vs-{$a}")->assertNotFound();     // only the alphabetical order exists
    $this->get('/vergelijk/audi-a4-vs-opel-corsa')->assertNotFound();   // not the same segment
    $this->get('/vergelijk/zomaar-vs-onzin')->assertNotFound();
    $this->get('/vergelijk/zonder-vs-twee-vs-drie')->assertNotFound();
});

it('links to comparisons from the model page and lists them in the sitemap', function () {
    givePrices();
    [$a, $b] = app(ComparisonService::class)->pairs()[0];
    $model = collect(app(ComparisonService::class)->find(ComparisonService::slug($a, $b)))->first();

    $this->get(route('model', [$model->brand, $model->slug]))->assertSee('Vergelijk de')->assertSee('/vergelijk/', false);
    $this->get('/sitemap.xml')->assertSee('/vergelijk/'.ComparisonService::slug($a, $b), false);
});

it('marks a model page without numbers as noindex', function () {
    $model = CarModel::where('slug', 'yaris')->first();
    DB::table('defect_stats')->where('car_model_id', $model->id)->delete();
    Cache::flush();

    $this->get('/toyota/yaris')->assertOk()->assertSee('noindex,follow', false);
    $this->get('/audi/a4')->assertOk()->assertDontSee('noindex', false);
});
