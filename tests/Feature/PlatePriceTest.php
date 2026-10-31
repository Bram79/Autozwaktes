<?php

use App\Models\CarModel;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

// --- one price on the page, not two that disagree -----------------------------------------------------------------------

/** Prices per build year for the Audi A4, so that the table can show the average of a year. */
function a4YearPrices(): void
{
    $id = CarModel::where('slug', 'a4')->value('id');
    DB::table('model_prices')->insert([
        ['car_model_id' => $id, 'build_year' => 2010, 'vehicles' => 300, 'average_price' => 47254],
        ['car_model_id' => $id, 'build_year' => 2011, 'vehicles' => 300, 'average_price' => 48000],
    ]);
    Cache::flush();
}

it('shows the average price of a build year in the table when nobody searched a plate', function () {
    a4YearPrices();

    $this->get('/audi/a4/b8/2010')->assertOk()->assertSee('Nieuwprijs bouwjaar 2010')->assertSee('€ 47.254')->assertDontSee('Nieuwprijs van jouw auto');
});

it('shows only the price of the searched car after a plate search, and leaves the average out of the table', function () {
    a4YearPrices();
    rdwCar(price: '41007');
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    foreach (['/audi/a4/b8/2010', '/audi/a4/b8/2011', '/audi/a4/b8'] as $url) {
        $html = $this->get($url)->assertOk()->getContent();

        expect($html)->toContain('Nieuwprijs van jouw auto')->toContain('€ 41.007');
        expect($html)->not->toContain('Nieuwprijs bouwjaar')->not->toContain('€ 47.254')->not->toContain('€ 48.000');
    }
});

it('keeps the average in the table when the RDW has no price for the searched car', function () {
    a4YearPrices();
    rdwCar(price: null);
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);

    $this->get('/audi/a4/b8/2010')->assertOk()->assertDontSee('Nieuwprijs van jouw auto')->assertSee('Nieuwprijs bouwjaar 2010')->assertSee('€ 47.254');
});

it('keeps the average in the table on the page of another model than the one that was searched', function () {
    rdwCar('LANCIA', 'DELTA', 2012, '27999');   // not one of ours: only the home page shows its price
    $this->post('/kenteken', ['kenteken' => 'AB-123-C']);
    DB::table('model_prices')->insert(['car_model_id' => CarModel::where('slug', 'a4')->value('id'), 'build_year' => 2010, 'vehicles' => 300, 'average_price' => 47254]);
    Cache::flush();

    $this->get('/audi/a4/b8/2010')->assertOk()->assertSee('Nieuwprijs bouwjaar 2010');
});
