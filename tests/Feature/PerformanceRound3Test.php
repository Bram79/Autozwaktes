<?php

use App\Models\PartsCategory;
use App\Models\Setting;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

/** Run $callback and return every query that was done, as [sql, bindings]. */
function queriesDuring(Closure $callback): array
{
    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = [$query->sql, $query->bindings];
    });
    $callback();

    return $queries;
}

// --- the same cache keys again and again ----------------------------------------------------------------------

it('reads the settings and the parts categories once per request, not once per defect row', function () {
    config(['cache.default' => 'database']);
    PartsCategory::create(['name' => 'Alles', 'keywords' => 'e', 'url' => 'https://parts.example/?auto={merk}']);
    config(['site.affiliates.parts.url' => 'https://parts.example/']);
    Cache::flush();
    $this->get('/audi/a4/b8')->assertOk();   // fills the cache
    app()->forgetScopedInstances();

    $queries = queriesDuring(fn () => $this->get('/audi/a4/b8')->assertOk());

    $reads = fn (string $key) => collect($queries)->filter(fn ($q) => preg_match('/from [`"]cache[`"]/', $q[0]) && collect($q[1])->contains(fn ($b) => is_string($b) && str_ends_with($b, $key)))->count();
    expect($reads('settings'))->toBeLessThanOrEqual(1);
    expect($reads('parts-categories'))->toBeLessThanOrEqual(1);
});

it('sees a changed setting right away', function () {
    expect(Setting::get('x'))->toBeNull();

    Setting::put('x', ['a' => 1]);

    expect(Setting::get('x'))->toBe(['a' => 1]);
});

it('sees a new parts category right away', function () {
    expect(PartsCategory::forDefect('Remleiding poreus'))->toBeNull();

    PartsCategory::create(['name' => 'Remmen', 'keywords' => 'rem', 'url' => 'https://parts.example/']);

    expect(PartsCategory::forDefect('Remleiding poreus')['name'])->toBe('Remmen');
});

// --- the ranking pages -----------------------------------------------------------------------------------------

it('does not ask the database once per brand on a ranking page', function () {
    $this->get('/ranglijst/alles')->assertOk();   // fills the cache

    $queries = queriesDuring(fn () => $this->get('/ranglijst/alles')->assertOk());

    expect(collect($queries)->filter(fn ($q) => str_contains($q[0], 'exists'))->count())->toBeLessThanOrEqual(2);
});

it('lists on a ranking page only the brands that have numbers', function () {
    $html = $this->get('/ranglijst/alles')->assertOk()->getContent();

    expect($html)->toContain('/ranglijst/merk/audi');
});

// --- index ----------------------------------------------------------------------------------------------------

it('has an index on build year and defect code for the average of the other models', function () {
    expect(Schema::hasIndex('defect_stats', ['build_year', 'defect_code']))->toBeTrue();
});
