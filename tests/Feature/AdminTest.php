<?php

use App\Filament\Pages\AffiliateSettings;
use App\Filament\Resources\Generations\Pages\ManageGenerations;
use App\Models\Generation;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->admin = User::factory()->create(['email' => 'beheer@example.com']);
    config(['site.admin_emails' => ['beheer@example.com']]);
});

it('sends guests to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('lets only listed e-mail addresses in outside the local environment', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['email' => 'other@example.com']))->get('/admin')->assertForbidden();
    $this->actingAs($this->admin)->get('/admin')->assertOk();
});

it('lets the admin edit the buying advice, and the site cache is cleared', function () {
    $this->actingAs($this->admin);
    $generation = Generation::where('slug', 'b8')->first();

    Cache::put('stats:top:1:all:all', 'old');

    Livewire::test(ManageGenerations::class)
        ->callAction(TestAction::make('edit')->table($generation), ['buying_advice' => 'Controleer de distributie.'])
        ->assertHasNoActionErrors();

    expect($generation->fresh()->buying_advice)->toBe('Controleer de distributie.');
    expect(Cache::get('stats:top:1:all:all'))->toBeNull();
});

it('rejects an end year before the start year', function () {
    $this->actingAs($this->admin);
    $generation = Generation::where('slug', 'b8')->first();

    Livewire::test(ManageGenerations::class)
        ->callAction(TestAction::make('edit')->table($generation), ['year_to' => 2001])
        ->assertHasActionErrors(['year_to']);
});

it('saves affiliate settings, which then show up on the site', function () {
    $this->actingAs($this->admin);

    Livewire::test(AffiliateSettings::class)
        ->fillForm(['insurance.url' => 'https://partner.example/x', 'insurance.title' => 'Mijn titel'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Setting::affiliate('insurance'))->toMatchArray(['url' => 'https://partner.example/x', 'title' => 'Mijn titel']);
    expect(Setting::affiliate('parts')['url'])->toBeNull();   // untouched blocks keep the config defaults
});
