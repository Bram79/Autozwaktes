<?php

use App\Models\Brand;
use App\Models\CarModel;
use Database\Seeders\ModelFixesSeeder;

function discovered(string $rdwBrand, string $name, string $slug, int $from, ?int $to = null): CarModel
{
    $brand = Brand::firstOrCreate(['rdw_name' => $rdwBrand], ['name' => ucfirst(strtolower($rdwBrand)), 'slug' => strtolower($rdwBrand)]);
    $model = $brand->carModels()->create(['name' => $name, 'slug' => $slug]);
    $model->aliases()->create(['pattern' => '^'.strtoupper($name).'( |$)', 'priority' => 100]);
    $model->generations()->create(['code' => 'Alle bouwjaren', 'slug' => 'alle-bouwjaren', 'year_from' => $from, 'year_to' => $to]);

    return $model;
}

it('merges duplicate models, fixes names and adds generations', function () {
    $ceed = discovered('KIA', 'Ceed', 'ceed', 2008);
    $cee = discovered('KIA', 'Cee', 'cee', 2007, 2018);
    discovered('TOYOTA', 'C-Hr', 'c-hr', 2016);
    discovered('VOLKSWAGEN', 'Vw', 'vw', 1953, 2014);

    $this->seed(ModelFixesSeeder::class);

    // Cee is gone and its alias now belongs to Ceed
    expect(CarModel::where('slug', 'cee')->exists())->toBeFalse();
    expect($ceed->aliases()->pluck('pattern')->all())->toContain('^CEE( |$)');

    // Ceed has real generations; the first one starts at the earliest year in the data (2007)
    expect($ceed->generations()->pluck('code')->all())->toBe(['ED', 'JD', 'CD']);
    expect($ceed->generations()->first()->year_from)->toBe(2007);

    expect(CarModel::where('slug', 'c-hr')->first()->name)->toBe('C-HR');
    expect(CarModel::where('slug', 'vw')->exists())->toBeFalse();
});

it('does not touch models whose generations were made by hand, and can run twice', function () {
    $focus = discovered('FORD', 'Focus', 'focus', 1998);
    $focus->generations()->update(['code' => 'Mijn generatie', 'slug' => 'mijn-generatie']);

    $this->seed(ModelFixesSeeder::class);
    $this->seed(ModelFixesSeeder::class);

    expect($focus->generations()->pluck('code')->all())->toBe(['Mijn generatie']);
});
