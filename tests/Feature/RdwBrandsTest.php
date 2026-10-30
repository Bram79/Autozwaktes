<?php

use App\Models\Brand;
use App\Rdw\BrandAdder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);

    Http::fake(['opendata.rdw.nl/*' => Http::response([
        ['merk' => 'VOLKSWAGEN', 'n' => '1250782'],
        ['merk' => 'BMW', 'n' => '510047'],
        ['merk' => 'MERCEDES-BENZ', 'n' => '471772'],
        ['merk' => 'CITROEN', 'n' => '378948'],
        ['merk' => 'LAND ROVER', 'n' => '56187'],
        ['merk' => '-', 'n' => '12'],
    ])]);
});

it('spells RDW brand names the way people write them', function () {
    expect(BrandAdder::displayName('MERCEDES-BENZ'))->toBe('Mercedes-Benz')
        ->and(BrandAdder::displayName('LAND ROVER'))->toBe('Land Rover')
        ->and(BrandAdder::displayName('CITROEN'))->toBe('Citroën')
        ->and(BrandAdder::displayName('SKODA'))->toBe('Škoda')
        ->and(BrandAdder::displayName('BMW'))->toBe('BMW')
        ->and(BrandAdder::displayName('VOLVO'))->toBe('Volvo');
});

it('shows which of the biggest brands are new, without adding them', function () {
    $this->artisan('rdw:brands --top=5')->assertSuccessful()->expectsOutputToContain('4 new brands');

    expect(Brand::where('rdw_name', 'BMW')->exists())->toBeFalse();
});

it('adds only the missing brands, with a clean slug, and does it once', function () {
    $this->artisan('rdw:brands --top=5 --add')->assertSuccessful()->expectsOutputToContain('4 brands added');

    expect(Brand::where('rdw_name', 'CITROEN')->first()->only(['name', 'slug']))->toBe(['name' => 'Citroën', 'slug' => 'citroen']);
    expect(Brand::where('rdw_name', 'MERCEDES-BENZ')->value('slug'))->toBe('mercedes-benz');
    expect(Brand::where('rdw_name', 'VOLKSWAGEN')->count())->toBe(1);      // the existing brand is left alone
    expect(Brand::where('rdw_name', '-')->exists())->toBeFalse();           // not a brand

    $this->artisan('rdw:brands --top=5 --add')->expectsOutputToContain('0 brands added');
    expect(Brand::where('rdw_name', 'BMW')->count())->toBe(1);
});

it('shows a new brand without models as an empty, noindex page and keeps it out of the ranking links', function () {
    $this->withoutVite();
    $this->artisan('rdw:brands --top=5 --add');

    $this->get('/bmw')->assertOk()->assertSee('noindex,follow', false);
    $this->get('/ranglijsten')->assertOk()->assertDontSee('BMW');
});
