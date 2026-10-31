<?php

use App\Livewire\SearchBox;
use App\Mail\ConfirmApkReminder;
use App\Rdw\RdwClient;
use App\Rdw\RdwUnavailableException;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    Cache::flush();
});

// --- search ---------------------------------------------------------------------------------------------------

it('stops answering searches from one visitor after too many in a minute', function () {
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    config(['site.limits.search_per_minute' => 3]);

    $component = Livewire::test(SearchBox::class);

    foreach (['a', 'au', 'aud'] as $query) {
        $component->set('query', $query);
    }
    $component->set('query', 'audi')->assertSee('Je zoekt te snel');
});

it('only searches on the first characters of a very long query', function () {
    $component = Livewire::test(SearchBox::class)->set('query', str_repeat('a', 5000));

    expect(mb_strlen($component->instance()->searchText()))->toBe(60);
});

// --- one e-mail address ---------------------------------------------------------------------------------------

it('sends at most a few confirmation mails per e-mail address per hour', function () {
    Mail::fake();
    config(['site.limits.apk_mail_per_hour' => 2]);
    Http::fake(['opendata.rdw.nl/*' => Http::response([[
        'kenteken' => 'AB123C', 'voertuigsoort' => 'Personenauto', 'merk' => 'AUDI', 'handelsbenaming' => 'A4',
        'datum_eerste_toelating_dt' => '2010-05-04T00:00:00.000', 'vervaldatum_apk_dt' => '2027-06-13T00:00:00.000',
    ]])]);

    foreach (range(1, 4) as $attempt) {
        // another IP address each time, so only the e-mail limit can stop it
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.$attempt"])
            ->post('/apk-herinnering', ['email' => 'slachtoffer@example.com', 'kenteken' => 'AB-123-C', 'akkoord' => '1'])
            ->assertOk()
            ->assertSee('Check je mail');
    }

    Mail::assertSent(ConfirmApkReminder::class, 2);
});

// --- the RDW --------------------------------------------------------------------------------------------------

it('asks the RDW only so many times per minute for all visitors together', function () {
    config(['site.limits.rdw_lookups_per_minute' => 2]);
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);
    $client = app(RdwClient::class);

    $client->findVehicle('AB123C');
    $client->findVehicle('AB123D');

    expect(fn () => $client->findVehicle('AB123E'))->toThrow(RdwUnavailableException::class);
    Http::assertSentCount(2);
});
