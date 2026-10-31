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
    expect(strpos($html, 'plek=kenteken-resultaat-generatie'))->toBeLessThan(strpos($html, '>Top 10 APK-gebreken</h2>'));
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

// --- B5: the vehicle report also appears when we have no numbers for the car ------------------------------------------

it('shows the vehicle report on the model page when the plate search ends there because there are no numbers for that year', function () {
    rdwHasAudi(1990);   // no generation of the A4 covers 1990

    $html = $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->getContent();

    expect($html)->toContain('plek=kenteken-resultaat');
});

it('does not show it on the model page to someone who did not search a plate', function () {
    $this->get('/audi/a4')->assertDontSee('plek=kenteken-resultaat', false);
});

it('shows the vehicle report for a car we do not know, using the make and model the RDW gave', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([[
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'LANCIA', 'handelsbenaming' => 'DELTA',
        'datum_eerste_toelating_dt' => '2012-05-04T00:00:00.000', 'vervaldatum_apk_dt' => '2027-06-13T00:00:00.000',
    ]])]);

    $html = $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->getContent();

    expect($html)->toContain('geen cijfers voor dit model');
    expect($html)->toContain('plek=kenteken-onbekend');
    expect($html)->toContain('Lancia Delta uit 2012');
    expect(strtoupper(json_encode(session()->all())))->not->toContain('AB123C');   // still no plate in the session
});

// --- the report and the price belong to the car that was searched, not to every page the visitor opens next -------------

it('talks about the searched car, with its own build year, also on another year page of that model', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $html = $this->get('/audi/a4/b8/2011')->assertOk()->getContent();

    expect($html)->toContain('Audi A4 uit 2010');          // the searched car, not the page
    expect($html)->not->toContain('Audi A4 uit 2011');
    expect($html)->toContain('plek=kenteken-resultaat');
});

it('shows neither the price nor the report of the searched car on the page of another model', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $html = $this->get('/volkswagen/golf/mk8')->assertOk()->getContent();

    expect($html)->not->toContain('Nieuwprijs van jouw auto')->not->toContain('plek=kenteken-resultaat');
    expect($html)->toContain('plek=generatiepagina-rapport');   // the normal block instead
});

it('keeps only the model number and year of the searched car in the session, never the plate', function () {
    rdwHasAudi(2010);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    expect(strtoupper(json_encode(session()->all())))->not->toContain('AB123C')->not->toContain('AB-123-C');
});

it('says in the privacy statement that the model and build year are kept for half an hour', function () {
    $this->get('/privacy')->assertSee('het model en bouwjaar', false);
});
