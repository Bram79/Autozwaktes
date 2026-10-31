<?php

use App\Mail\ApkReminderMail;
use App\Models\ApkReminder;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    RateLimiter::clear('plate:127.0.0.1');
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}+{model}&jaar={jaar}']);
});

// --- K3: the plate never goes to the session -------------------------------------------------------------------------

it('does not keep the plate in the session after a failed search', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);

    $this->from('/')->post('/kenteken', ['kenteken' => 'QQ-123-R'])->assertSessionHas('plate_error');

    expect(strtoupper(json_encode(session()->all())))->not->toContain('QQ-123-R')->not->toContain('QQ123R');
    expect(session()->getOldInput())->not->toHaveKey('kenteken');
});

it('does not keep the plate in the session after a failed APK reminder request', function () {
    Mail::fake();
    rdwHasAudi();

    // no consent ticked: validation fails and Laravel would flash the input
    $this->from('/apk-herinnering')->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'QQ-123-R'])->assertSessionHasErrors('akkoord');

    expect(strtoupper(json_encode(session()->all())))->not->toContain('QQ-123-R');
    expect(session()->getOldInput())->not->toHaveKey('kenteken');

});

it('does not keep the plate in the session when the plate for an APK reminder does not exist', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);

    $this->from('/apk-herinnering')->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'QQ-123-R', 'akkoord' => 1])->assertSessionHasErrors('kenteken');

    expect(strtoupper(json_encode(session()->all())))->not->toContain('QQ-123-R');
    expect(session()->getOldInput())->toHaveKey('email')->not->toHaveKey('kenteken');   // the address stays, so nobody has to type it again
});

// --- K4: the APK reminder does what the privacy statement says ---------------------------------------------------------

it('says in the form and in the privacy statement that the reminder comes every year', function () {
    $this->get('/apk-herinnering')->assertSee('elk jaar', false)->assertSee('uitschrijven', false);
    $this->get('/privacy')->assertSee('elk jaar', false)->assertSee('meer dan een jaar', false);
});

it('reminds again the next year, after the APK was renewed', function () {
    Mail::fake();
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => '2026-01-01', 'apk_expires_at' => '2027-06-13', 'last_reminded_for' => '2027-06-13']);

    Carbon::setTestNow('2028-05-20');
    rdwHasAudi(2010, '2028-06-13');   // renewed: new date within 30 days
    $this->artisan('apk:remind')->assertSuccessful();

    Mail::assertSent(ApkReminderMail::class, 1);
    expect(ApkReminder::first()->last_reminded_for->toDateString())->toBe('2028-06-13');
    Carbon::setTestNow();
});

it('deletes the data when the APK has been expired for more than a year', function () {
    Mail::fake();
    Carbon::setTestNow('2027-05-20');
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => '2025-01-01', 'apk_expires_at' => '2025-06-01']);
    ApkReminder::create(['email' => 'b@example.com', 'license_plate' => 'CD456E', 'token' => 't2', 'confirmed_at' => '2025-01-01', 'apk_expires_at' => '2027-03-01']);

    Http::fake(['opendata.rdw.nl/*' => function ($request) {
        $plate = $request['$where'] ?? '';

        // the first car was never renewed (sold or scrapped), the second expired a few months ago
        return Http::response([[
            'kenteken' => str_contains($plate, 'AB123C') ? 'AB123C' : 'CD456E',
            'voertuigsoort' => 'Personenauto',
            'vervaldatum_apk_dt' => (str_contains($plate, 'AB123C') ? '2025-06-01' : '2027-03-01').'T00:00:00.000',
        ]]);
    }]);

    $this->artisan('apk:remind')->assertSuccessful();

    expect(ApkReminder::pluck('email')->all())->toBe(['b@example.com']);
    Mail::assertNothingSent();
    Carbon::setTestNow();
});
