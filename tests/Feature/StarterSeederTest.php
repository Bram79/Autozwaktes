<?php

use App\Models\Brand;
use App\Models\CarModel;
use Database\Seeders\StarterModelsSeeder;

it('seeds the ten starter models', function () {
    $this->seed(StarterModelsSeeder::class);

    expect(CarModel::count())->toBe(10);
    expect(Brand::count())->toBe(8);
});

it('can be run twice without creating duplicates', function () {
    $this->seed(StarterModelsSeeder::class);
    $this->seed(StarterModelsSeeder::class);

    expect(CarModel::count())->toBe(10);
});

it('finds the right generation for a build year', function () {
    $this->seed(StarterModelsSeeder::class);

    $a4 = CarModel::where('slug', 'a4')->firstOrFail();

    expect($a4->generations->first(fn ($g) => $g->coversYear(2010))->code)->toBe('B8');
    expect($a4->generations->first(fn ($g) => $g->coversYear(2022))->code)->toBe('B9');
});

it('has no overlapping generation years within a model', function () {
    $this->seed(StarterModelsSeeder::class);

    foreach (CarModel::with('generations')->get() as $model) {
        $previousEnd = null;
        foreach ($model->generations as $generation) {
            if ($previousEnd !== null) {
                expect($generation->year_from)->toBeGreaterThan($previousEnd, "{$model->name} {$generation->code}");
            }
            $previousEnd = $generation->year_to;
        }
    }
});
