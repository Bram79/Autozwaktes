<?php

use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
});

it('does not say that no e-mail address is asked, because the APK reminder asks one', function () {
    $this->get('/privacy')->assertOk()
        ->assertDontSee('geen account, naam of e-mailadres', false)
        ->assertSee('alleen als je een APK-herinnering aanvraagt', false);
});

it('has a date that is not in the future', function () {
    $html = $this->get('/privacy')->getContent();

    preg_match('/Laatst bijgewerkt: (\d{1,2}) (\w+) (\d{4})/u', $html, $m);
    $months = ['januari' => 1, 'februari' => 2, 'maart' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6, 'juli' => 7, 'augustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'december' => 12];

    expect($m)->not->toBeEmpty();
    // a day of margin: the server clock is UTC, the owner lives in the Netherlands
    expect(Carbon::create((int) $m[3], $months[$m[2]], (int) $m[1])->lte(now()->addDay()->endOfDay()))->toBeTrue();
});

it('says that we keep no license plate, and what we do keep for speed', function () {
    $this->get('/privacy')->assertSee('We bewaren je kenteken niet', false)->assertSee('zonder kenteken', false);
});

it('only says that there are no advertising cookies when ads are off', function () {
    $this->get('/privacy')->assertSee('geen advertentie- of volgcookies', false);

    config(['site.ads.enabled' => true]);
    $this->get('/privacy')->assertDontSee('geen advertentie- of volgcookies', false)->assertSee('alleen met jouw toestemming', false);
});

it('names who is responsible and how to reach them', function () {
    config(['site.operator_name' => 'Bram Meijer', 'site.contact_email' => 'info@autozwaktes.example']);

    $html = $this->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('Wie is verantwoordelijk')->toContain('Bram Meijer')->toContain('info@autozwaktes.example');
});

it('lists who receives data, how long things are kept and which rights you have', function () {
    $this->get('/privacy')->assertOk()
        ->assertSee('Met wie delen we gegevens', false)->assertSee('RDW', false)->assertSee('hostingpartij', false)->assertSee('mailprovider', false)
        ->assertSee('Hoe lang bewaren we gegevens', false)->assertSee('2 uur', false)->assertSee('14 dagen', false)
        ->assertSee('bezwaar', false)->assertSee('overdragen', false)->assertSee('intrekken', false);
});

it('explains the session without IP address and browser, and the limit on the plate search', function () {
    // the session holds no IP address or browser (AnonymousSessionHandler); the limit works with the IP address for a minute
    $this->get('/privacy')->assertSee('zonder je IP-adres of browser', false)->assertSee('binnen een uur', false)
        ->assertDontSee('sessie met je IP-adres', false);
});

it('says that a removed reminder can stay in a backup for a while', function () {
    $this->get('/privacy')->assertSee('back-up', false)->assertSee('14 dagen', false);
});

it('does not pass site:check on the live site without a responsible person and contact address', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['app.debug' => false, 'app.url' => 'https://autozwaktes.example', 'mail.default' => 'smtp', 'site.admin_emails' => ['ik@example.com'],
        'site.contact_email' => null, 'site.operator_name' => null]);

    $this->artisan('site:check')->assertFailed()->expectsOutputToContain('CONTACT_EMAIL')->expectsOutputToContain('OPERATOR_NAME');

    // with a person and an address filled in, only the other live requirements are left
    config(['site.contact_email' => 'info@autozwaktes.example', 'site.operator_name' => 'Bram Meijer',
        'site.backup_passphrase' => 'een-lange-zin', 'session.secure' => true]);
    $this->artisan('site:check')->assertSuccessful();
});

it('lists every cookie that the site sets, with its purpose and how long it lasts', function () {
    $response = $this->get('/privacy')->assertOk()->assertSee('auto-zwaktes-session', false)->assertSee('XSRF-TOKEN', false);

    // the cookies on the page are the cookies that the site really sets
    $names = collect($this->get('/')->headers->getCookies())->map->getName()->all();
    expect($names)->toContain('XSRF-TOKEN');
    $response->assertDontSee('az_consent', false);   // only while ads and the banner are on

    config(['site.ads.enabled' => true]);
    $this->get('/privacy')->assertSee('az_consent', false);
});

it('tells that the e-mail address code for the limit, the logs and the backups are handled with care', function () {
    $this->get('/privacy')->assertSee('onleesbare code van je e-mailadres', false)
        ->assertSee('geen e-mailadres of kenteken', false)
        ->assertSee('versleuteld', false)
        ->assertSee('Advertentie · Partnerlink', false);
});
