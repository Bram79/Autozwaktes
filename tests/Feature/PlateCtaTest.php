<?php

use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    RateLimiter::clear('plate:127.0.0.1');
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}+{model}&jaar={jaar}']);
});

/** The RDW knows a plate: an Audi A4 from the given year, APK valid until $apk. */
if (! function_exists('rdwHasAudi')) {
    function rdwHasAudi(int $year = 2010, string $apk = '2027-06-13'): void
    {
        Http::fake(['opendata.rdw.nl/*' => Http::response([[
            'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4',
            'datum_eerste_toelating_dt' => "{$year}-05-04T00:00:00.000", 'vervaldatum_apk_dt' => "{$apk}T00:00:00.000",
        ]])]);
    }
}

// --- K6 and K7: the vehicle report after a plate search ---------------------------------------------------------------

it('shows the vehicle report above the table on the year page after a plate search', function () {
    rdwHasAudi(2010);

    $html = $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->getContent();

    $cta = strpos($html, 'plek=kenteken-resultaat');
    expect($cta)->not->toBeFalse();
    expect($cta)->toBeLessThan(strpos($html, 'Meest voorkomende APK-gebreken'));   // before the defect table
    expect(substr_count($html, 'plek=kenteken-resultaat'))->toBe(1);              // and not twice
});

it('keeps the vehicle report visible when the page is reloaded or opened again, for a while', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $this->get('/audi/a4/b8/2010')->assertSee('plek=kenteken-resultaat', false);
    $this->get('/audi/a4/b8/2010')->assertSee('plek=kenteken-resultaat', false);     // reload
    $this->get('/audi/a4/b8/2011')->assertSee('plek=kenteken-resultaat', false);     // another year of the same car

    Carbon::setTestNow(now()->addMinutes(31));
    $this->get('/audi/a4/b8/2010')->assertDontSee('plek=kenteken-resultaat', false)->assertSee('plek=zijbalk', false);
    Carbon::setTestNow();
});

it('also shows it prominently on the generation page the visitor lands on, with its own spot in the click statistics', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    expect($html)->toContain('plek=kenteken-resultaat-generatie');
    expect(strpos($html, 'plek=kenteken-resultaat-generatie'))->toBeLessThan(strpos($html, 'Top 10 APK-gebreken'));
});

it('does not show the prominent report to someone who did not search a plate', function () {
    $this->get('/audi/a4/b8/2010')->assertDontSee('kenteken-resultaat', false);
    $this->get('/audi/a4/b8')->assertDontSee('kenteken-resultaat', false);
});

it('does not remember anything about the plate itself, only that a search was done', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'QQ-123-R']);

    expect(strtoupper(json_encode(session()->all())))->not->toContain('QQ123R')->not->toContain('QQ-123-R');
});
