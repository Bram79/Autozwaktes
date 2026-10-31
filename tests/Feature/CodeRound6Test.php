<?php

use App\Filament\Widgets\SiteStatus;
use App\Jobs\CalculateStatisticsJob;
use App\Models\ImportRun;
use App\Models\User;
use App\Rdw\RdwClient;
use App\Site\RateGate;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

// --- a calculation that failed must be seen ---------------------------------------------------------------------------

it('writes down that the calculation failed, so that the admin sees it', function () {
    (new CalculateStatisticsJob)->failed(new RuntimeException('Deadlock found when trying to get lock'));

    $run = ImportRun::where('status', 'failed')->first();
    expect($run->dataset)->toBe('statistics');
    expect($run->error)->toContain('RuntimeException')->toContain('Deadlock');
});

it('shows in the admin that the last attempt failed, until a later one has worked', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));
    ImportRun::create(['dataset' => 'defects', 'status' => 'finished', 'started_at' => now()->subDays(2), 'finished_at' => now()->subDays(2)]);
    (new CalculateStatisticsJob)->failed(new RuntimeException('Out of memory'));

    Livewire::test(SiteStatus::class)->assertSee('mislukt')->assertSee('Out of memory');

    ImportRun::create(['dataset' => 'statistics', 'status' => 'finished', 'started_at' => now(), 'finished_at' => now()]);

    Livewire::test(SiteStatus::class)->assertDontSee('mislukt');
});

// --- limits in one place ---------------------------------------------------------------------------------------------

it('takes the limit for the plate search and the APK form from the config', function () {
    config(['site.limits.plate_per_minute' => 2]);
    RateLimiter::clear('plate:127.0.0.1');
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);

    foreach (range(1, 2) as $i) {
        $this->post('/kenteken', ['kenteken' => 'QQ-123-R'])->assertSessionHas('plate_error', 'We vonden geen auto met dit kenteken.');
    }
    $this->post('/kenteken', ['kenteken' => 'QQ-123-R'])->assertSessionHas('plate_error', 'Je zoekt te snel. Probeer het over een minuut opnieuw.');
});

it('takes the limit for the APK form from the config', function () {
    config(['site.limits.apk_request_per_minute' => 1]);
    RateLimiter::clear('apk-reminder:127.0.0.1');

    $this->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'AB-123-C']);   // the first one: fails on the consent, fine
    $this->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'AB-123-C'])->assertSessionHasErrors(['email' => 'Je probeert het te vaak. Wacht een minuut en probeer het opnieuw.']);
});

it('has one small class for "so many per minute"', function () {
    RateLimiter::clear('test-gate');

    expect(RateGate::attempt('test-gate', 2, 60))->toBeTrue();
    expect(RateGate::attempt('test-gate', 2, 60))->toBeTrue();
    expect(RateGate::attempt('test-gate', 2, 60))->toBeFalse();
    expect(RateGate::attempt('test-gate', 0, 60))->toBeFalse();   // 0 allowed: nobody gets in
});

// --- constants -------------------------------------------------------------------------------------------------------

it('has one name for the vehicle type that the site is about', function () {
    expect(RdwClient::PASSENGER_CAR)->toBe('Personenauto');
});
