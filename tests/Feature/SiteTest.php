<?php

use App\Models\Brand;
use App\Models\DefectCode;
use App\Models\Generation;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);

    foreach (['101' => 'Remschijf versleten', '102' => 'Uitlaat lekt'] as $code => $desc) {
        DefectCode::create(['code' => $code, 'description' => $desc]);
    }

    $this->b8 = Generation::whereHas('carModel', fn ($q) => $q->where('slug', 'a4'))->where('slug', 'b8')->first();
    $this->b8->update(['buying_advice' => "Let op de distributieketting.\nVraag om onderhoudsboekje."]);

    // A4 B8 2010: 1000 petrol cars, 40% remschijf, 10% uitlaat. Only 50 diesels: too few to show.
    foreach ([['Benzine', 1000], ['Diesel', 50]] as [$fuel, $total]) {
        DB::table('vehicle_counts')->insert(['car_model_id' => $this->b8->car_model_id, 'build_year' => 2010, 'fuel' => $fuel, 'total_vehicles' => $total]);
    }
    foreach ([['101', 400], ['102', 100]] as [$code, $n]) {
        DB::table('defect_stats')->insert(['car_model_id' => $this->b8->car_model_id, 'generation_id' => $this->b8->id, 'build_year' => 2010, 'fuel' => 'Benzine', 'defect_code' => $code, 'vehicles_with_defect' => $n, 'total_vehicles' => 1000, 'percentage' => $n / 10]);
    }

    // another model, same years: the "average of similar cars"
    $golf = Generation::whereHas('carModel', fn ($q) => $q->where('slug', 'golf'))->where('slug', 'mk6')->first();
    DB::table('vehicle_counts')->insert(['car_model_id' => $golf->car_model_id, 'build_year' => 2010, 'fuel' => 'Benzine', 'total_vehicles' => 1000]);
    DB::table('defect_stats')->insert(['car_model_id' => $golf->car_model_id, 'generation_id' => $golf->id, 'build_year' => 2010, 'fuel' => 'Benzine', 'defect_code' => '101', 'vehicles_with_defect' => 200, 'total_vehicles' => 1000, 'percentage' => 20]);
});

it('shows the home page with a search form', function () {
    $this->get('/')->assertOk()->assertSee('Zwakke punten per automodel')->assertSee('role="search"', false);
});

it('lists brands, models and generations with data', function () {
    $this->get('/audi')->assertOk()->assertSee('Audi A4');
    $this->get('/audi/a4')->assertOk()->assertSee('B8')->assertDontSee('B5');
});

it('returns 404 for unknown pages and generations without data', function () {
    $this->get('/bmw')->assertNotFound();
    $this->get('/audi/a4/b5')->assertNotFound();
    $this->get('/audi/a4/b8/1999')->assertNotFound();
});

it('shows the top defects with percentages, average, checklist and advice on the generation page', function () {
    $this->get('/audi/a4/b8')
        ->assertOk()
        ->assertSee('Audi A4 B8 (2008–2015): zwakke punten en APK-problemen')
        ->assertSee('Remschijf versleten')
        ->assertSee('38,1%')        // 400 of 1050 vehicles
        ->assertSee('vaker dan gemiddeld')
        ->assertSee('Koopchecklist')
        ->assertSee('Let op de distributieketting.')
        ->assertSee('Bron: RDW open data (CC-0)')
        ->assertSee('<link rel="canonical" href="'.url('/audi/a4/b8').'"', false)
        ->assertSee('BreadcrumbList', false)
        ->assertSee('"@type":"Dataset"', false);
});

it('filters on build year and fuel through the url, also without JavaScript', function () {
    $this->get('/audi/a4/b8?brandstof=Benzine')->assertOk()->assertSee('40,0%')->assertSee('1.000 voertuigen');
    $this->get('/audi/a4/b8?brandstof=Diesel')->assertOk()->assertSee('38,1%');   // diesel is not offered as filter (too few cars): ignored, shows everything
    $this->get('/audi/a4/b8?jaar=2010')->assertOk()->assertSee('<option value="2010" selected', false);
});

it('hides results when there are too few vehicles', function () {
    config(['site.min_sample' => 5000]);

    $this->get('/audi/a4/b8')->assertOk()->assertSee('te weinig voertuigen')->assertDontSee('Remschijf versleten');
});

it('shows a year page with a canonical url and links to other years', function () {
    $this->get('/audi/a4/b8/2010')
        ->assertOk()
        ->assertSee('Audi A4 B8 (2010): APK-gebreken en zwakke punten', false)
        ->assertSee('<link rel="canonical" href="'.url('/audi/a4/b8/2010').'"', false);
});

it('renders affiliate blocks only when a url is configured, with sponsored nofollow', function () {
    $this->get('/audi/a4/b8')->assertDontSee('Affiliate-link');

    config(['site.affiliates.insurance.url' => 'https://partner.example/verzekering?auto={merk}']);

    $this->get('/audi/a4/b8')
        ->assertSee('rel="sponsored nofollow noopener"', false)
        ->assertSee('https://partner.example/verzekering?auto=Audi', false)
        ->assertSee('Affiliate-link');
});

it('finds models through the search box', function () {
    Livewire\Livewire::test(App\Livewire\SearchBox::class)
        ->set('query', 'audi a4')
        ->assertSee('Audi A4')
        ->set('query', 'zzzz')
        ->assertSee('Geen resultaten');
});

it('serves a sitemap and robots.txt', function () {
    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/audi/a4/b8'))->assertSee(url('/audi/a4/b8/2010'))->assertDontSee('/audi/a4/b5');
    $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'));
});
