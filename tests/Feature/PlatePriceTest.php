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

/** The RDW knows this plate, with this catalogue price (null = the RDW has no price). */
function rdwCar(string $brand = 'AUDI', string $model = 'A4', int $year = 2010, ?string $price = '38450'): void
{
    Http::fake(['opendata.rdw.nl/*' => Http::response([array_filter([
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => $brand, 'handelsbenaming' => $model,
        'datum_eerste_toelating_dt' => "{$year}-05-04T00:00:00.000", 'vervaldatum_apk_dt' => '2027-06-13T00:00:00.000',
        'catalogusprijs' => $price,
    ])])]);
}

it('asks the RDW for the catalogue price of the plate', function () {
    rdwCar();

    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    Http::assertSent(fn ($request) => str_contains($request['$select'] ?? '', 'catalogusprijs'));
});

it('shows the new price of the exact car after a plate search', function () {
    rdwCar(price: '38450');

    $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])
        ->assertOk()
        ->assertSee('Nieuwprijs van jouw auto')
        ->assertSee('€ 38.450');
});

it('keeps showing it for half an hour, and not to someone who did not search a plate', function () {
    rdwCar(price: '38450');
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $this->get('/audi/a4/b8/2011')->assertSee('€ 38.450');
    $this->get('/audi/a4/b8')->assertSee('€ 38.450');

    Carbon::setTestNow(now()->addMinutes(31));
    $this->get('/audi/a4/b8/2011')->assertDontSee('Nieuwprijs van jouw auto');
    Carbon::setTestNow();

    $this->flushSession();
    $this->get('/audi/a4/b8/2011')->assertDontSee('Nieuwprijs van jouw auto');
});

it('shows no price box when the RDW has no price for the car', function () {
    rdwCar(price: null);
    $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->assertDontSee('Nieuwprijs van jouw auto');

    Cache::flush();
    rdwCar(price: '0');
    $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->assertDontSee('Nieuwprijs van jouw auto');
});

it('shows it on the model page when there are no numbers for that year', function () {
    rdwCar(year: 1990, price: '12000');

    $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->assertSee('Nieuwprijs van jouw auto')->assertSee('€ 12.000');
});

it('shows it for a car we have no numbers for', function () {
    rdwCar('LANCIA', 'DELTA', 2012, '27999');

    $this->followingRedirects()->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertOk()->assertSee('geen cijfers voor dit model')->assertSee('€ 27.999');
});

it('keeps the plate itself out of the session, while the price may be in it', function () {
    rdwCar();
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    expect(strtoupper(json_encode(session()->all())))->not->toContain('AB123C')->not->toContain('AB-123-C');
});

it('tells in the privacy statement that the price of the car is shown and for how long it is remembered', function () {
    $this->get('/privacy')->assertSee('catalogusprijs', false);
});
