<?php

use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
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

// --- K5: proxies ---------------------------------------------------------------------------------------------------

it('ignores forwarded headers unless proxies are trusted', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);
    RateLimiter::clear('plate:10.0.0.5');
    RateLimiter::clear('plate:203.0.113.9');

    $request = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.9', 'X-Forwarded-Proto' => 'https'])
        ->post('/kenteken', ['kenteken' => 'AB123C']);

    // not trusted: the visitor can not choose his own address, and a fake "https" is not believed
    $request()->assertHeaderMissing('Strict-Transport-Security');
    expect(RateLimiter::attempts('plate:10.0.0.5'))->toBe(1)->and(RateLimiter::attempts('plate:203.0.113.9'))->toBe(0);

    // trusted (the server really is behind a proxy): the visitor's own address and the https connection count
    config(['trustedproxy.proxies' => '*']);
    $request()->assertHeader('Strict-Transport-Security');
    expect(RateLimiter::attempts('plate:203.0.113.9'))->toBe(1);
});

it('reads the trusted proxies from the environment settings', function () {
    expect(config('trustedproxy'))->toHaveKey('proxies');
    expect(file_get_contents(base_path('.env.production.example')))->toContain('TRUSTED_PROXIES=');
});
