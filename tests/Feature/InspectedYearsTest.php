<?php

use App\Models\Generation;
use App\Site\AdviceWriter;
use App\Site\StatsService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-31');   // first APK after 4 years: build year 2022 is the last one that has had an APK
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);

    $this->generation = Generation::whereHas('carModel', fn ($q) => $q->where('slug', 'golf'))->where('slug', 'mk8')->firstOrFail();

    // lots of new cars (2023 to 2025) that have never had an APK, so without any defects
    foreach ([2023, 2024, 2025] as $year) {
        DB::table('vehicle_counts')->insert(['car_model_id' => $this->generation->car_model_id, 'build_year' => $year, 'fuel' => 'Benzine', 'total_vehicles' => 20000]);
    }
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

it('leaves build years without an APK out of the percentages, otherwise they are far too low', function () {
    $result = app(StatsService::class)->topDefects($this->generation);

    $inspected = (int) DB::table('vehicle_counts')->where('car_model_id', $this->generation->car_model_id)
        ->whereBetween('build_year', [2020, 2022])->sum('total_vehicles');

    expect($result['total'])->toBe($inspected);
    $first = $result['defects']->first();
    expect($first->percentage)->toBe(round($first->vehicles / $inspected * 100, 1));
});

it('has no statistics for one build year that has not had an APK yet', function () {
    expect(app(StatsService::class)->topDefects($this->generation, 2025)['enough_data'])->toBeFalse();
    expect(app(StatsService::class)->comparison($this->generation, 2025)['enough_data'])->toBeFalse();
    expect(app(AdviceWriter::class)->rows($this->generation, 2025))->toBe([]);
});

it('shows only the explanation on the year page of a car that has not had an APK yet, and keeps it out of the index', function () {
    $html = $this->get('/volkswagen/golf/mk8/2025')->assertOk()->getContent();

    expect($html)->toContain('hebben nog geen APK gehad');
    expect($html)->not->toContain('<table')->not->toContain('Wat de cijfers zeggen')->not->toContain('Geen enkel veelvoorkomend gebrek');
    expect($html)->toContain('<meta name="robots" content="noindex,follow">');
});

it('keeps the year page of a car with an APK as it was', function () {
    $html = $this->get('/volkswagen/golf/mk8/2021')->assertOk()->getContent();

    expect($html)->toContain('<table')->not->toContain('noindex');
});

it('does not put years without an APK in the sitemap', function () {
    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('/volkswagen/golf/mk8/2021')->not->toContain('/volkswagen/golf/mk8/2025');
});
