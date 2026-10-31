<?php

use App\Models\CarModel;
use App\Site\ComparisonService;
use App\Site\RankingService;
use App\Site\Seo;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-31');
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

// --- titles ---------------------------------------------------------------------------------------------------

it('does not add "APK-gebreken" to a title that already says it', function () {
    $title = Seo::title('Minste APK-gebreken bij Mercedes-Benz: ranglijst per generatie | Auto Zwaktes');

    expect(mb_strlen($title))->toBeLessThanOrEqual(60);
    expect(substr_count($title, 'APK-gebreken'))->toBe(1);
    expect($title)->toContain('Mercedes-Benz');
});

it('drops the site name from a long title before it cuts into the text', function () {
    expect(Seo::title("Auto's op benzine met de minste APK-gebreken: ranglijst | Auto Zwaktes"))->toBe("Auto's op benzine met de minste APK-gebreken: ranglijst");
});

// --- ranking pages --------------------------------------------------------------------------------------------

it('does not call a build year list "betrouwbaarst", because the data only holds APK defects', function () {
    $year = RankingService::lastInspectedYear() - 3;

    $html = $this->get("/ranglijst/bouwjaar/{$year}")->assertOk()->getContent();

    expect($html)->not->toContain('Betrouwbaarste')->not->toContain('betrouwbaarste');
    expect($html)->toContain("Occasions uit {$year} met de minste APK-gebreken");
});

it('says on every ranking what the score does not measure', function () {
    foreach (['/ranglijst/alles', '/ranglijst/merk/audi'] as $url) {
        $this->get($url)->assertOk()->assertSee('kilometers, onderhoud of gebruik', false)->assertSee('indicatie', false);
    }
});

it('says "alle auto\'s op deze site", because the average is not of every car in the country', function () {
    $this->get('/ranglijst/alles')->assertSee("alle auto's op deze site", false);
});

it('names fuels as a person would', function () {
    expect(Seo::fuelCars('Elektriciteit'))->toBe("elektrische auto's");
    expect(Seo::fuelCars('Benzine'))->toBe("benzineauto's");
    expect(Seo::fuelCars('Diesel'))->toBe("dieselauto's");
    expect(Seo::fuelCars('Hybride'))->toBe("hybride auto's");
    expect(Seo::fuelCars('Waterstof'))->toBe("waterstof-auto's");   // unknown fuel: a safe fallback
});

// --- comparison page ------------------------------------------------------------------------------------------

it('words the comparison as "lower", not as "better", and says what it leaves out', function () {
    // pairs are made of models with a similar new price
    foreach (DB::table('vehicle_counts')->select('car_model_id', 'build_year')->distinct()->get() as $row) {
        DB::table('model_prices')->insert(['car_model_id' => $row->car_model_id, 'build_year' => $row->build_year, 'vehicles' => 500, 'average_price' => 20000]);
    }
    Cache::flush();
    [$a, $b] = app(ComparisonService::class)->pairs()[0];
    $html = $this->get(route('comparison', ComparisonService::slug($a, $b)))->assertOk()->getContent();

    expect($html)->not->toContain('dus beter')->not->toContain('Minste gebreken</span>');
    expect($html)->toContain('lager')->toContain('kilometers, onderhoud');
    expect($html)->not->toMatch("/\\w's en [\\d.]+ \\w+'s uit/");   // "A1's" and "Focus's" are no Dutch plurals
    expect($html)->toContain('bouwjaren');
    expect($html)->toContain('kan boven de 100 uitkomen');
});

// --- the strip at the top -------------------------------------------------------------------------------------

it('does not make the site look official in the top strip', function () {
    $this->get('/')->assertDontSee('Echte APK-data van de RDW')->assertSee('Gebaseerd op open data van de RDW');
});

// --- model page grammar ---------------------------------------------------------------------------------------

it('says "Er is 1 generatie" for one generation', function () {
    $model = CarModel::where('slug', 'a4')->with('brand')->first();
    $keep = $model->generations()->first();
    $model->generations()->where('id', '!=', $keep->id)->delete();
    Cache::flush();
    $html = $this->get(route('model', [$model->brand, $model->slug]))->assertOk()->getContent();

    expect($html)->toContain('Er is 1 generatie')->not->toContain('Er zijn 1 generatie');
});
