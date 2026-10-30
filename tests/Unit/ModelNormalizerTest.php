<?php

use App\Rdw\ModelNormalizer;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);
    $this->normalizer = new ModelNormalizer;
});

it('maps messy Audi A4 names to one model', function (string $raw) {
    $a4 = App\Models\CarModel::where('slug', 'a4')->value('id');

    expect($this->normalizer->match('AUDI', $raw))->toBe($a4);
})->with(['A4', 'A4 AVANT', 'A4 Avant', 'A4 LIMOUSINE', 'A4 74 KW', 'A4 ALLROAD QUATTRO', '  a4   cabrio ']);

it('maps Polo variants including codes and brand prefixes', function (string $raw) {
    $polo = App\Models\CarModel::where('slug', 'polo')->value('id');

    expect($this->normalizer->match('VOLKSWAGEN', $raw))->toBe($polo);
})->with(['POLO', 'POLO 44 KW', 'POLO COUPE FOX 40 KW E2', '861011 POLO', 'VOLKSWAGEN POLO']);

it('does not match other models', function (string $brand, string $raw) {
    expect($this->normalizer->match($brand, $raw))->toBeNull();
})->with([
    ['VOLKSWAGEN', 'ID. POLO 155KW'],   // electric ID. range, not a Polo
    ['AUDI', 'A40'],
    ['AUDI', 'S4'],
    ['BMW', 'A4'],                      // brand we don't track
    ['VOLKSWAGEN', 'POLOS'],
]);
