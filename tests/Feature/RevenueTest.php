<?php

use App\Filament\Pages\AffiliateClicks;
use App\Mail\ApkReminderMail;
use App\Mail\ConfirmApkReminder;
use App\Models\AffiliateClick;
use App\Models\ApkReminder;
use App\Models\CarModel;
use App\Models\PartsCategory;
use App\Models\User;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    RateLimiter::clear('apk-reminder:127.0.0.1');
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}+{model}&jaar={jaar}']);
});

/** The RDW knows this plate, with this APK expiry date. */
function rdwKnows(string $apkExpires = '2027-06-13'): void
{
    Http::fake(['opendata.rdw.nl/*' => Http::response([[
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4',
        'datum_eerste_toelating_dt' => '2010-05-04T00:00:00.000', 'vervaldatum_apk_dt' => $apkExpires.'T00:00:00.000',
    ]])]);
}

// --- click tracking -------------------------------------------------------------------------------------------

it('counts a click and sends the visitor to the partner, with the car filled in', function () {
    $this->get('/go/vehicle_report?plek=zijbalk&p=/audi/a4/b8&merk=Audi&model=A4&jaar=2010')
        ->assertRedirect('https://partner.example/rapport?auto=Audi+A4&jaar=2010')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    $click = AffiliateClick::first();
    expect($click->partner)->toBe('vehicle_report')->and($click->page)->toBe('/audi/a4/b8')->and($click->position)->toBe('zijbalk');
});

it('stores nothing personal with a click', function () {
    expect(Schema::getColumnListing('affiliate_clicks'))->toBe(['id', 'partner', 'page', 'position', 'created_at']);
});

it('does not count bots, and gives a 404 for unknown or switched off partners', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/go/vehicle_report')->assertRedirect();
    expect(AffiliateClick::count())->toBe(0);

    $this->get('/go/does_not_exist')->assertNotFound();
    $this->get('/go/insurance')->assertNotFound();   // no url set
    config(['site.affiliates.vehicle_report.enabled' => false]);
    $this->get('/go/vehicle_report')->assertNotFound();
});

it('only sends visitors to addresses set in the admin, never to an address from the link', function () {
    $location = $this->get('/go/vehicle_report?url=https://evil.example')->assertRedirect()->headers->get('Location');

    expect($location)->toStartWith('https://partner.example/')->not->toContain('evil.example');
});

it('puts every affiliate block behind /go, with sponsored nofollow and a visible notice', function () {
    $response = $this->get('/audi/a4/b8')->assertOk();

    $response->assertSee('/go/vehicle_report?', false)->assertSee('rel="sponsored nofollow noopener"', false)->assertSee('ontvangen wij een vergoeding')
        ->assertDontSee('partner.example', false);
});

it('shows the vehicle report prominently after a license plate search, and on model pages', function () {
    $this->withSession(['from_plate_until' => now()->addMinutes(10)->timestamp, 'from_plate_model' => CarModel::where('slug', 'a4')->value('id')])->get('/audi/a4/b8/2010')->assertSee('kenteken-resultaat', false);
    $this->flushSession();
    $this->get('/audi/a4/b8/2010')->assertDontSee('kenteken-resultaat', false);

    $this->get('/audi/a4')->assertSee('plek=modelpagina', false);
});

it('gives a defect a parts link when a category keyword matches, and counts that click as a parts click', function () {
    $category = PartsCategory::create(['name' => 'Remmen', 'keywords' => 'rem, remleiding', 'url' => 'https://parts.example/remmen?auto={merk}']);
    config(['site.affiliates.parts.url' => 'https://parts.example/']);

    $this->get('/audi/a4/b8')->assertSee('Onderdelen: remmen', false)->assertSee("cat={$category->id}", false)->assertSee('(partnerlink)', false);

    $this->get("/go/parts?cat={$category->id}&merk=Audi&p=/audi/a4/b8")->assertRedirect('https://parts.example/remmen?auto=Audi');
    expect(AffiliateClick::first()->position)->toBe('gebrek: Remmen');
});

it('shows the clicks per partner, page and spot in the admin', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));
    AffiliateClick::insert([
        ['partner' => 'vehicle_report', 'page' => '/audi/a4', 'position' => 'modelpagina', 'created_at' => now()],
        ['partner' => 'vehicle_report', 'page' => '/audi/a4', 'position' => 'modelpagina', 'created_at' => now()],
        ['partner' => 'insurance', 'page' => '/ford/focus', 'position' => 'zijbalk', 'created_at' => now()->subDays(40)],   // outside 30 days
    ]);

    Livewire::test(AffiliateClicks::class)->assertSee('/audi/a4')->assertSee('modelpagina')->assertDontSee('/ford/focus');
});

it('shows which partner gets clicks on which spot, and the clicks per day', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));
    AffiliateClick::insert([
        ['partner' => 'vehicle_report', 'page' => '/audi/a4', 'position' => 'modelpagina', 'created_at' => '2026-10-30 10:00:00'],
        ['partner' => 'vehicle_report', 'page' => '/audi/a4', 'position' => 'zijbalk', 'created_at' => '2026-10-30 11:00:00'],
        ['partner' => 'insurance', 'page' => '/audi/a4', 'position' => 'zijbalk', 'created_at' => '2026-10-29 11:00:00'],
    ]);
    Carbon::setTestNow('2026-10-31 12:00:00');

    $data = Livewire::test(AffiliateClicks::class)->instance()->getViewData();
    Carbon::setTestNow();

    expect($data['combos']->map(fn ($r) => "{$r->partner}|{$r->position}|{$r->clicks}")->all())
        ->toContain('vehicle_report|modelpagina|1', 'vehicle_report|zijbalk|1', 'insurance|zijbalk|1');
    expect($data['perDay']->pluck('clicks', 'day')->all())->toBe(['2026-10-29' => 1, '2026-10-30' => 2]);
});

// --- advertisements and cookie banner ---------------------------------------------------------------------------

it('has no cookie banner and no ad spots while ads are off', function () {
    $this->get('/audi/a4/b8')->assertDontSee('consent-banner', false)->assertDontSee('data-ad-slot', false);
});

it('shows a cookie banner and empty hidden ad spots when ads are on, but loads no ad script on the server', function () {
    config(['site.ads.enabled' => true, 'site.ads.script_url' => 'https://ads.example/ads.js']);

    $html = $this->get('/audi/a4/b8')->assertOk()
        ->assertSee('consent-banner', false)->assertSee('Alleen noodzakelijke cookies')->assertSee('Cookie-instellingen')
        ->assertSee('data-ad-slot="zijbalk" hidden', false)
        ->getContent();

    // the address is only a meta tag: no <script src> to the ad network before the visitor has said yes
    expect($html)->toContain('<meta name="ads-script" content="https://ads.example/ads.js">');
    expect($html)->not->toContain('src="https://ads.example');
});

// --- APK reminder ---------------------------------------------------------------------------------------------

it('needs consent, a valid plate and an e-mail address for an APK reminder', function () {
    Mail::fake();
    rdwKnows();

    $this->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'AB-123-C'])->assertSessionHasErrors('akkoord');
    $this->post('/apk-herinnering', ['email' => 'nope', 'kenteken' => 'AB-123-C', 'akkoord' => 1])->assertSessionHasErrors('email');
    $this->post('/apk-herinnering', ['email' => 'a@example.com', 'kenteken' => 'AB', 'akkoord' => 1])->assertSessionHasErrors('kenteken');

    Mail::assertNothingSent();
    expect(ApkReminder::count())->toBe(0);
});

it('sends only a confirmation mail first (double opt-in) and gives the same answer for a known address', function () {
    Mail::fake();
    rdwKnows('2027-06-13');
    $form = ['email' => 'Anna@Example.com', 'kenteken' => 'ab-123-c', 'akkoord' => 1];

    $this->post('/apk-herinnering', $form)->assertOk()->assertSee('Check je mail');

    $reminder = ApkReminder::first();
    expect($reminder->email)->toBe('anna@example.com')->and($reminder->license_plate)->toBe('AB123C')
        ->and($reminder->apk_expires_at->toDateString())->toBe('2027-06-13')->and($reminder->isConfirmed())->toBeFalse();
    Mail::assertSent(ConfirmApkReminder::class, 1);
    Mail::assertNotSent(ApkReminderMail::class);

    // known and unconfirmed: one record, a new confirmation mail
    $this->post('/apk-herinnering', $form)->assertOk()->assertSee('Check je mail');
    expect(ApkReminder::count())->toBe(1);

    // known and confirmed: the same answer, but no mail (nobody can find out who is on the list)
    $reminder->update(['confirmed_at' => now()]);
    $this->post('/apk-herinnering', $form)->assertOk()->assertSee('Check je mail');
    Mail::assertSent(ConfirmApkReminder::class, 2);
});

it('does not confirm on a plain visit of the link, because mail scanners open links too', function () {
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123']);

    $this->get(URL::temporarySignedRoute('apk.confirm', now()->addDay(), ['token' => 'abc123']))
        ->assertOk()
        ->assertSee('Bevestig mijn herinnering');
    expect($reminder->fresh()->isConfirmed())->toBeFalse();
});

it('confirms when the visitor presses the button, and refuses a link that was not signed by us', function () {
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123']);

    $this->get('/apk-herinnering/bevestig/abc123')->assertForbidden();
    $this->post('/apk-herinnering/bevestig/abc123')->assertForbidden();
    expect($reminder->fresh()->isConfirmed())->toBeFalse();

    $this->post(URL::temporarySignedRoute('apk.confirm.store', now()->addDay(), ['token' => 'abc123']))
        ->assertOk()
        ->assertSee('Je herinnering staat aan');
    expect($reminder->fresh()->isConfirmed())->toBeTrue();
});

it('does not unsubscribe on a plain visit of the link', function () {
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123', 'confirmed_at' => now()]);

    $this->get(URL::signedRoute('apk.unsubscribe', ['token' => 'abc123']))
        ->assertOk()
        ->assertSee('Ja, schrijf me uit');
    expect(ApkReminder::count())->toBe(1);
});

it('deletes the e-mail address and plate when someone presses the unsubscribe button', function () {
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123', 'confirmed_at' => now()]);

    $this->get('/apk-herinnering/uitschrijven/abc123')->assertForbidden();
    $this->post('/apk-herinnering/uitschrijven/abc123')->assertForbidden();
    expect(ApkReminder::count())->toBe(1);

    $this->post(URL::signedRoute('apk.unsubscribe.store', ['token' => 'abc123']))->assertOk()->assertSee('Je bent uitgeschreven');
    expect(ApkReminder::count())->toBe(0);
});

it('reminds once, about 30 days before the APK expires, with an unsubscribe link', function () {
    Mail::fake();
    Carbon::setTestNow('2027-05-20');
    rdwKnows('2027-06-13');   // 24 days away
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123', 'confirmed_at' => now(), 'apk_expires_at' => '2027-06-13']);

    $this->artisan('apk:remind')->assertSuccessful();
    $this->artisan('apk:remind')->assertSuccessful();   // a second run the same day sends nothing new

    Mail::assertSent(ApkReminderMail::class, 1);
    Mail::assertSent(ApkReminderMail::class, function (ApkReminderMail $mail) {
        $mail->assertSeeInHtml('AB123C');
        $mail->assertSeeInHtml('13 juni 2027');
        $mail->assertSeeInHtml('apk-herinnering/uitschrijven/abc123');
        $mail->assertDontSeeInHtml('affiliate-link');   // partner block is off
        expect($mail->headers()->text)->toHaveKey('List-Unsubscribe');

        return true;
    });
    expect($reminder->fresh()->last_reminded_for->toDateString())->toBe('2027-06-13');
    Carbon::setTestNow();
});

it('does not remind when the APK was renewed, and does not remind while it is still far away', function () {
    Mail::fake();
    Carbon::setTestNow('2027-05-20');
    ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123', 'confirmed_at' => now(), 'apk_expires_at' => '2027-06-13']);
    ApkReminder::create(['email' => 'b@example.com', 'license_plate' => 'CD456E', 'token' => 'def456', 'confirmed_at' => now(), 'apk_expires_at' => '2028-01-01']);

    rdwKnows('2029-06-13');   // the RDW now says: renewed until 2029
    $this->artisan('apk:remind')->assertSuccessful();

    Mail::assertNothingSent();
    expect(ApkReminder::where('license_plate', 'AB123C')->first()->apk_expires_at->toDateString())->toBe('2029-06-13');
    Carbon::setTestNow();
});

it('puts the partner block in the mail only when it is switched on, with a counted link', function () {
    config(['site.affiliates.apk_mail.enabled' => true, 'site.affiliates.apk_mail.url' => 'https://garage.example/']);
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'abc123', 'apk_expires_at' => now()->addDays(10)]);

    $html = (new ApkReminderMail($reminder))->render();

    expect($html)->toContain('Binnenkort APK?')->toContain('/go/apk_mail')->toContain('affiliate-link')->not->toContain('garage.example');

    $this->get('/go/apk_mail?plek=apk-mail&p=/mail')->assertRedirect('https://garage.example/');
    expect(AffiliateClick::first()->position)->toBe('apk-mail');
});

it('deletes requests that nobody confirmed within a week, and a plate that no longer exists', function () {
    Mail::fake();
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);
    $old = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 'a1']);
    $old->forceFill(['created_at' => now()->subDays(8)])->save();
    ApkReminder::create(['email' => 'b@example.com', 'license_plate' => 'CD456E', 'token' => 'b2']);
    ApkReminder::create(['email' => 'c@example.com', 'license_plate' => 'EF789G', 'token' => 'c3', 'confirmed_at' => now(), 'apk_expires_at' => now()->addDays(5)]);

    $this->artisan('apk:remind')->assertSuccessful();

    expect(ApkReminder::pluck('email')->all())->toBe(['b@example.com']);
});

it('keeps the new pages out of the way of the brand routes and lists them in the sitemap', function () {
    $this->get('/apk-herinnering')->assertOk()->assertSee('Nooit meer je APK vergeten');
    $this->get('/sitemap.xml')->assertSee('/apk-herinnering');
    $this->get('/robots.txt')->assertSee('Disallow: /go/');
});

it('shows at most a few parts links in one defect table, so the table stays a table and not a shop window', function () {
    PartsCategory::create(['name' => 'Alles', 'keywords' => 'e', 'url' => 'https://parts.example/?auto={merk}']);
    config(['site.affiliates.parts.url' => 'https://parts.example/', 'site.parts_links_per_table' => 2]);

    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    expect(substr_count($html, '(partnerlink)'))->toBe(2);
});
