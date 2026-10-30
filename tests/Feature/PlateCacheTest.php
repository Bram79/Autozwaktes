<?php

use App\Console\Commands\CacheCleanExpired;
use App\Site\PlateLookup;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);
    // the real cache table, so that we can look at what is stored
    config(['cache.default' => 'database']);
    Cache::flush();
});

function rdwHasThisAudi(): void
{
    Http::fake(['opendata.rdw.nl/*' => Http::response([[
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4',
        'datum_eerste_toelating_dt' => '2010-05-04T00:00:00.000', 'vervaldatum_apk_dt' => '2027-06-13T00:00:00.000',
    ]])]);
}

it('does not keep the license plate or its APK date in the cache', function () {
    rdwHasThisAudi();

    expect(app(PlateLookup::class)->find('ab-123-c')['status'])->toBeIn(['found', 'no_stats']);   // the car is known (the demo stats are not loaded here)

    $rows = DB::table('cache')->get();
    $stored = strtoupper($rows->map(fn ($row) => $row->key.' '.$row->value)->implode(' '));

    expect($rows->contains(fn ($row) => str_contains(strtoupper($row->value), 'AUDI')))->toBeTrue();   // the car data is cached ...
    expect($stored)->not->toContain('AB123C')->not->toContain('AB-123-C');                            // ... without the plate
    expect($stored)->not->toContain('2027-06-13');                                                     // and without the APK date of that one car
});

it('uses a cache key that can not be worked out from the plate by anyone who does not have the app key', function () {
    rdwHasThisAudi();
    app(PlateLookup::class)->find('AB123C');

    $keys = DB::table('cache')->pluck('key')->implode(' ');

    expect($keys)->not->toContain(sha1('AB123C'));
    expect($keys)->toContain(hash_hmac('sha256', 'AB123C', config('app.key')));
});

it('still caches the answer, so the RDW is asked only once', function () {
    rdwHasThisAudi();

    app(PlateLookup::class)->find('AB123C');
    app(PlateLookup::class)->find('ab-123-c');

    Http::assertSentCount(1);
});

it('does not write the plate to the log when the RDW does not answer', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out for https://opendata.rdw.nl/resource/m9d7-ebf2.json?%24where=kenteken+%3D+%27AB123C%27'));
    Log::spy();

    expect(app(PlateLookup::class)->find('AB123C')['status'])->toBe('unavailable');

    Log::shouldHaveReceived('error')->withArgs(fn ($message) => ! str_contains(strtoupper($message), 'AB123C') && ! str_contains($message, 'kenteken'))->atLeast()->once();
});

it('removes expired cache rows, and only those', function () {
    DB::table('cache')->insert([
        ['key' => 'old-plate-ratelimit', 'value' => 's:1:"1";', 'expiration' => now()->subHour()->timestamp],
        ['key' => 'old-model', 'value' => 's:1:"1";', 'expiration' => now()->subDay()->timestamp],
        ['key' => 'still-valid', 'value' => 's:1:"1";', 'expiration' => now()->addHour()->timestamp],
    ]);

    $this->artisan('cache:clean-expired')->assertSuccessful()->expectsOutputToContain('2 expired');

    expect(DB::table('cache')->pluck('key')->all())->toBe(['still-valid']);
});

it('cleans the cache every hour', function () {
    $events = collect(app(Schedule::class)->events())->filter(fn ($event) => str_contains($event->command, 'cache:clean-expired'));

    expect($events)->toHaveCount(1);
    expect($events->first()->expression)->toBe('0 * * * *');
    expect(CacheCleanExpired::class)->toBeString();
});
