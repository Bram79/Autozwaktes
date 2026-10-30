<?php

use App\Models\Generation;
use App\Models\PartsCategory;
use App\Site\AdviceWriter;
use App\Site\ComparisonService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();

    // the RDW name of the defect that comes first in the demo data becomes a CNG defect
    $this->first = DB::table('defect_stats')->where('build_year', 2010)->where('car_model_id', Generation::where('slug', 'b8')->value('car_model_id'))
        ->groupBy('defect_code')->orderByRaw('sum(vehicles_with_defect) desc')->value('defect_code');
    DB::table('defect_codes')->where('code', $this->first)->update(['description' => 'CNG-/LNG- tank heeft deuk(en)']);
    Cache::flush();
});

it('shows the defect table, the checklist and the figures without the abbreviation', function () {
    $html = $this->get('/audi/a4/b8/2010')->assertOk()->getContent();

    expect($html)->toContain('Aardgastank heeft deuk(en)');             // in the table
    expect($html)->toContain('Controleer: aardgastank heeft deuk(en)'); // in the checklist
    expect($html)->not->toContain('CNG')->not->toContain('LNG');
});

it('gives the figures table and the facts the plain name too', function () {
    $generation = Generation::where('slug', 'b8')->first();

    $lines = collect(app(AdviceWriter::class)->rows($generation, 2010))->pluck('lines')->flatten()->implode(' ');
    expect($lines)->toContain('Aardgastank heeft deuk(en)')->not->toContain('CNG');
    expect(json_encode(app(AdviceWriter::class)->facts($generation, 2010)))->not->toContain('CNG');
});

it('shows the plain name on the comparison page', function () {
    // two models that are compared get the same defect names
    DB::table('model_prices')->delete();
    foreach (DB::table('vehicle_counts')->select('car_model_id', 'build_year')->distinct()->get() as $row) {
        DB::table('model_prices')->insert(['car_model_id' => $row->car_model_id, 'build_year' => $row->build_year, 'vehicles' => 500, 'average_price' => 20000]);
    }
    Cache::flush();
    [$a, $b] = app(ComparisonService::class)->pairs()[0] ?? [null, null];

    expect($a)->not->toBeNull();
    $html = $this->get('/vergelijk/'.ComparisonService::slug($a, $b))->assertOk()->getContent();

    expect($html)->not->toContain('CNG-/LNG-')->not->toContain('CNG/LNG');
});

it('still finds the parts category from the RDW name, not from the plain name', function () {
    PartsCategory::create(['name' => 'Gas', 'keywords' => 'CNG', 'url' => 'https://parts.example/gas']);
    config(['site.affiliates.parts.url' => 'https://parts.example/']);
    Cache::flush();

    $this->get('/audi/a4/b8/2010')->assertSee('Onderdelen: gas', false);
});
