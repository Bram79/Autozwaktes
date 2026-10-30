<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Rdw\ModelDiscoverer;
use App\Rdw\ModelNormalizer;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\DB;

/** Insert $count vehicles of one model and build year. */
function fleet(string $brand, string $model, int $year, int $count): void
{
    static $n = 0;

    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = ['license_plate' => 'D'.++$n, 'brand_raw' => $brand, 'model_raw' => $model, 'build_year' => $year, 'fuel' => 'Benzine'];
    }
    DB::table('staging_vehicles')->insert($rows);
}

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);
    Brand::firstOrCreate(['rdw_name' => 'KIA'], ['name' => 'Kia', 'slug' => 'kia']);
    Brand::firstOrCreate(['rdw_name' => 'FORD'], ['name' => 'Ford', 'slug' => 'ford']);
});

it('creates a model with an alias and one generation from the vehicle data', function () {
    fleet('KIA', 'CEED', 2012, 30);
    fleet('KIA', 'CEED SPORTSWAGON', 2014, 30);
    fleet('KIA', 'CEED', 2020, 15);
    fleet('KIA', 'CEED', 1900, 1);   // a typo in the data is ignored

    $discoverer = app(ModelDiscoverer::class);
    $found = $discoverer->discover(50);

    expect($found)->toHaveCount(1);
    expect($found[0])->toMatchArray(['name' => 'Ceed', 'slug' => 'ceed', 'vehicles' => 76, 'year_from' => 2012, 'year_to' => 2020]);

    expect($discoverer->create($found))->toBe(1);

    $model = CarModel::where('slug', 'ceed')->first();
    expect($model->aliases)->toHaveCount(1);
    expect($model->generations->first()->only(['code', 'year_from', 'year_to']))
        ->toBe(['code' => 'Alle bouwjaren', 'year_from' => 2012, 'year_to' => 2020]);

    // the alias now matches both spellings
    expect(app(ModelNormalizer::class)->match('KIA', 'CEED SPORTSWAGON'))->toBe($model->id);
});

it('skips models we already have and models with too few vehicles', function () {
    fleet('VOLKSWAGEN', 'POLO', 2010, 60);   // starter model
    fleet('KIA', 'RARE', 2010, 20);

    expect(app(ModelDiscoverer::class)->discover(50))->toBe([]);
});

it('keeps two words for names that start with a prefix like GRAND', function () {
    fleet('FORD', 'GRAND C-MAX', 2012, 60);
    fleet('FORD', 'C-MAX', 2012, 60);

    $names = collect(app(ModelDiscoverer::class)->discover(50))->pluck('name')->all();

    expect($names)->toContain('Grand C-Max', 'C-Max');
});

it('does not touch a model or generations that already exist when run twice', function () {
    fleet('KIA', 'CEED', 2012, 60);
    $discoverer = app(ModelDiscoverer::class);

    $discoverer->create($discoverer->discover(50));
    $before = CarModel::count();

    expect(app(ModelDiscoverer::class)->discover(50))->toBe([]);   // matched by the new alias
    expect(CarModel::count())->toBe($before);
});

it('runs the discover command in dry-run mode without creating anything', function () {
    fleet('KIA', 'CEED', 2012, 60);

    $this->artisan('rdw:discover --min=50 --dry-run')->assertSuccessful();

    expect(CarModel::where('slug', 'ceed')->exists())->toBeFalse();
});

it('treats a version letter like CORSA-E as part of the model', function () {
    fleet('KIA', 'PICANTO-B', 2012, 60);
    fleet('KIA', 'PICANTO', 2012, 60);

    $found = app(ModelDiscoverer::class)->discover(50);

    expect($found)->toHaveCount(1);
    expect($found[0]['name'])->toBe('Picanto');
});
