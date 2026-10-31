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
