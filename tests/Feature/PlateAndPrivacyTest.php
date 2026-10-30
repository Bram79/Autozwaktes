<?php

use App\Models\CarModel;
use App\Rdw\RdwClient;
use App\Site\PlateLookup;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    RateLimiter::clear('plate:127.0.0.1');
});

/** Pretend the RDW knows this car (null = unknown plate). */
function fakeRdw(?array $vehicle): void
{
    $client = Mockery::mock(RdwClient::class);
    $client->shouldReceive('findVehicle')->andReturn($vehicle);
    app()->instance(RdwClient::class, $client);
}

it('normalizes plates', function () {
    expect(PlateLookup::normalize('ab-123-c'))->toBe('AB123C');
    expect(PlateLookup::normalize(' 12 abc 3'))->toBe('12ABC3');
});

it('sends the visitor to the year page of the car', function () {
    fakeRdw(['voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4 AVANT', 'datum_eerste_toelating_dt' => '2010-05-04T00:00:00.000']);

    $response = $this->post('/kenteken', ['kenteken' => 'ab-123-c']);

    $response->assertRedirect(route('year', ['audi', 'a4', 'b8', 2010]));
});

it('shows a message for an invalid plate without calling the RDW', function () {
    $client = Mockery::mock(RdwClient::class);
    $client->shouldNotReceive('findVehicle');
    app()->instance(RdwClient::class, $client);

    $this->from('/')->post('/kenteken', ['kenteken' => 'abc'])
        ->assertRedirect('/')
        ->assertSessionHas('plate_error');
});

it('shows a message when the plate does not exist or is not a passenger car', function () {
    fakeRdw(null);
    $this->post('/kenteken', ['kenteken' => 'AB123C'])->assertSessionHas('plate_error', 'We vonden geen auto met dit kenteken.');

    Cache::flush();
    fakeRdw(['voertuigsoort' => 'Motorfiets', 'merk' => 'HONDA', 'handelsbenaming' => 'CB', 'datum_eerste_toelating_dt' => '2010-01-01']);
    $this->post('/kenteken', ['kenteken' => 'AB123C'])->assertSessionHas('plate_error');
});

it('says so when the model is not one of ours', function () {
    fakeRdw(['voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'R8', 'datum_eerste_toelating_dt' => '2015-01-01']);

    $this->post('/kenteken', ['kenteken' => 'AB123C'])
        ->assertSessionHas('plate_error', 'We hebben deze auto gevonden, maar we hebben (nog) geen cijfers voor dit model.');
});

it('limits the number of lookups per minute', function () {
    fakeRdw(null);

    foreach (range(1, 10) as $i) {
        $this->post('/kenteken', ['kenteken' => 'AB123C']);
    }

    $this->post('/kenteken', ['kenteken' => 'AB123C'])->assertSessionHas('plate_error', 'Je zoekt te snel. Probeer het over een minuut opnieuw.');
});

it('shows the plate form on the home page and the privacy page', function () {
    $this->get('/')->assertOk()->assertSee('Of zoek op kenteken')->assertSee('Privacy');
    $this->get('/privacy')->assertOk()->assertSee('Privacyverklaring');
    $this->get('/sitemap.xml')->assertSee('/privacy');
});

it('only lists models that have statistics', function () {
    // the demo data covers some starter models, so a model without data must not show up
    $this->get('/volkswagen')->assertOk();

    $model = CarModel::where('slug', 'yaris')->first();
    DB::table('defect_stats')->where('car_model_id', $model->id)->delete();
    Cache::flush();

    $this->get('/toyota')->assertDontSee('Yaris');
});
