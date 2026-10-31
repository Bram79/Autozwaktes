<?php

use App\Filament\Pages\AffiliateSettings;
use App\Livewire\GenerationStats;
use App\Mail\ApkReminderMail;
use App\Models\AffiliateClick;
use App\Models\ApkReminder;
use App\Models\Generation;
use App\Models\User;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}']);
});

// --- admin ----------------------------------------------------------------------------------------------------

it('sends the security headers on the admin too, so it cannot be put in a frame', function () {
    $response = $this->get('/admin/login');

    expect($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN');
    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();   // Filament needs inline scripts
});

it('only accepts web addresses as affiliate address', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));

    config(['site.affiliates.vehicle_report.url' => null]);   // the form starts from the config: its example address has {merk} in it

    Livewire::test(AffiliateSettings::class)->fillForm(['insurance.url' => 'javascript:alert(1)'])->call('save')->assertHasFormErrors(['insurance.url']);
    Livewire::test(AffiliateSettings::class)->fillForm(['insurance.url' => 'ftp://partner.example/x'])->call('save')->assertHasFormErrors(['insurance.url']);
    Livewire::test(AffiliateSettings::class)->fillForm(['insurance.url' => 'https://partner.example/x'])->call('save')->assertHasNoFormErrors();
});

// --- /go ------------------------------------------------------------------------------------------------------

it('keeps redirecting but stops counting when one visitor clicks too often', function () {
    config(['site.limits.go_per_minute' => 2]);

    foreach (range(1, 4) as $i) {
        $this->get('/go/vehicle_report?plek=zijbalk&p=/audi/a4')->assertRedirect();
    }

    expect(AffiliateClick::count())->toBe(2);
});

it('does not count requests that are no customers', function (string $method, ?string $agent) {
    $this->call($method, '/go/vehicle_report?plek=zijbalk', [], [], [], ['HTTP_USER_AGENT' => $agent ?? ''])->assertRedirect();

    expect(AffiliateClick::count())->toBe(0);
})->with([
    'HEAD request' => ['HEAD', 'Mozilla/5.0'],
    'no user agent' => ['GET', null],
    'facebook preview' => ['GET', 'facebookexternalhit/1.1'],
    'curl' => ['GET', 'curl/8.4.0'],
    'python script' => ['GET', 'python-requests/2.31'],
    'headless browser' => ['GET', 'Mozilla/5.0 HeadlessChrome/120'],
    'monitor' => ['GET', 'UptimeMonitor/1.0'],
]);

it('counts a normal browser', function () {
    $this->call('GET', '/go/vehicle_report?plek=zijbalk', [], [], [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh) Safari/605'])->assertRedirect();

    expect(AffiliateClick::count())->toBe(1);
});

it('only saves page and spot in the usual shape, so nobody can fill the statistics with their own text', function () {
    $this->get('/go/vehicle_report?plek=zijbalk&p=/audi/a4/b8');
    $this->get('/go/vehicle_report?plek=<script>x</script>&p=javascript:alert(1)');
    $this->get('/go/vehicle_report?plek=zijbalk&p=/audi?kenteken=AB123C');

    expect(AffiliateClick::orderBy('id')->get(['page', 'position'])->map->only(['page', 'position'])->all())->toBe([
        ['page' => '/audi/a4/b8', 'position' => 'zijbalk'],
        ['page' => '/', 'position' => 'onbekend'],
        ['page' => '/', 'position' => 'zijbalk'],
    ]);
});

// --- plate field ----------------------------------------------------------------------------------------------

it('does not crash on an array for the plate', function () {
    RateLimiter::clear('plate:127.0.0.1');

    $this->post('/kenteken', ['kenteken' => ['a', 'b']])->assertRedirect(route('home'))->assertSessionHas('plate_error');
});

// --- logs without personal data -------------------------------------------------------------------------------

it('logs a failed confirmation mail without the e-mail address or the plate', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([[
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4',
        'datum_eerste_toelating_dt' => '2010-05-04T00:00:00.000', 'vervaldatum_apk_dt' => '2027-06-13T00:00:00.000',
    ]])]);
    Mail::shouldReceive('to')->andThrow(new RuntimeException('Connection refused for slachtoffer@example.com plate AB123C'));
    $logged = [];
    Log::shouldReceive('error')->andReturnUsing(function ($message) use (&$logged) {
        $logged[] = (string) $message;
    });

    $this->post('/apk-herinnering', ['email' => 'slachtoffer@example.com', 'kenteken' => 'AB-123-C', 'akkoord' => '1'])
        ->assertSessionHasErrors(['email' => 'Het versturen van de mail is niet gelukt. Probeer het straks nog eens.']);

    expect($logged)->not->toBeEmpty();
    expect(implode(' ', $logged))->not->toContain('slachtoffer')->not->toContain('AB123C')->not->toContain('AB-123-C');
});

it('keeps sending reminders when one mail fails, and logs it without personal data', function () {
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => now(), 'apk_expires_at' => now()->addDays(10)]);
    ApkReminder::create(['email' => 'b@example.com', 'license_plate' => 'CD456E', 'token' => 't2', 'confirmed_at' => now(), 'apk_expires_at' => now()->addDays(12)]);
    Http::fake(function ($request) {
        $plate = str_contains((string) $request->url(), 'AB123C') ? 'AB123C' : 'CD456E';

        return Http::response([['kenteken' => $plate, 'voertuigsoort' => 'Personenauto', 'vervaldatum_apk_dt' => now()->addDays($plate === 'AB123C' ? 10 : 12)->toDateString().'T00:00:00.000']]);
    });
    $sent = [];
    Mail::shouldReceive('to')->andReturnUsing(function ($email) use (&$sent) {
        if ($email === 'a@example.com') {
            throw new RuntimeException("SMTP failed for {$email}");
        }
        $sent[] = $email;

        return new class
        {
            public function send($mailable): void {}
        };
    });
    $logged = [];
    Log::shouldReceive('error')->andReturnUsing(function ($message) use (&$logged) {
        $logged[] = (string) $message;
    });

    $this->artisan('apk:remind')->assertSuccessful();

    expect($sent)->toBe(['b@example.com']);
    expect(implode(' ', $logged))->not->toContain('a@example.com')->not->toContain('AB123C');
});

it('labels the partner block in the reminder mail as an advertisement above the block', function () {
    config(['site.affiliates.apk_mail.enabled' => true, 'site.affiliates.apk_mail.url' => 'https://garage.example/']);
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => now(), 'apk_expires_at' => now()->addDays(10)]);

    $html = (new ApkReminderMail($reminder))->render();

    expect(strpos($html, 'Advertentie · Partnerlink'))->toBeInt()->toBeLessThan(strpos($html, '/go/apk_mail'));
});

// --- Livewire -------------------------------------------------------------------------------------------------

it('does not let the browser change which generation a table is for', function () {
    $generation = Generation::where('slug', 'b8')->first();

    expect(fn () => Livewire::test(GenerationStats::class, ['generationId' => $generation->id])->set('generationId', $generation->id + 1))
        ->toThrow(Exception::class, 'Cannot update locked property');
});
