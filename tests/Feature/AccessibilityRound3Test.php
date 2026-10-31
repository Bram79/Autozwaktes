<?php

use App\Livewire\GenerationStats;
use App\Livewire\SearchBox;
use App\Models\Generation;
use App\Models\PartsCategory;
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
    RateLimiter::clear('plate:127.0.0.1');
});

// --- focus ----------------------------------------------------------------------------------------------------

it('keeps a focused element from hiding behind the sticky header or the cookie banner', function () {
    $css = file_get_contents(resource_path('css/app.css'));

    expect($css)->toContain('scroll-padding-top')->toContain('scroll-padding-bottom');
});

it('gives the search, filter and e-mail fields the clear focus line of the site instead of a weak ring', function (string $view) {
    expect(file_get_contents(resource_path("views/{$view}.blade.php")))->not->toContain('focus:outline-none');
})->with(['livewire/search-box', 'livewire/generation-stats']);

it('gives the e-mail field and the checkbox of the APK reminder a focus line and a border that can be seen', function () {
    $html = $this->get('/apk-herinnering')->assertOk()->getContent();

    expect($html)->toContain('border-slate-500 text-brand-700');   // the checkbox: a border of at least 3:1 against white
    expect($html)->not->toContain('border-slate-400');
    expect($html)->not->toMatch('/id="email"[^>]*focus:outline-none/');
});

// --- announcements --------------------------------------------------------------------------------------------

it('has the live region for the search results on the page from the start, so a screen reader announces what appears', function () {
    Livewire::test(SearchBox::class)->assertSeeHtml('role="status"')->assertSeeHtml('aria-live="polite"');
});

it('tells how many results there are', function () {
    Livewire::test(SearchBox::class)->set('query', 'audi')->assertSeeHtml('<p class="sr-only">1 resultaat</p>');
    Livewire::test(SearchBox::class)->set('query', 'a')->assertSeeHtml(' resultaten</p>');
    Livewire::test(SearchBox::class)->set('query', 'zzzzzz')->assertSee('Geen resultaten');
});

it('announces that the table was updated when a filter changes', function () {
    $generation = Generation::where('slug', 'b8')->first();

    Livewire::test(GenerationStats::class, ['generationId' => $generation->id])
        ->assertSeeHtml('role="status"')
        ->set('year', '2010')
        ->assertSee('Tabel bijgewerkt voor bouwjaar 2010');
});

// --- forms ----------------------------------------------------------------------------------------------------

it('connects the error of the APK form to its field, and puts the errors in a block that is announced', function () {
    $html = $this->post('/apk-herinnering', ['email' => 'geen-mailadres', 'kenteken' => 'AB-123-C'])->assertRedirect();
    $html = $this->get('/apk-herinnering')->assertOk()->getContent();

    expect($html)->toContain('id="fouten"')->toContain('role="alert"');
    expect($html)->toContain('id="email" name="email"');
    expect($html)->toMatch('/id="email"[^>]*aria-invalid="true"[^>]*aria-describedby="email-fout"|id="email"[^>]*aria-describedby="email-fout"[^>]*aria-invalid="true"/');
    expect($html)->toContain('id="email-fout"');
});

it('has no aria-invalid on fields that are fine', function () {
    expect($this->get('/apk-herinnering')->getContent())->not->toContain('aria-invalid="true"');
});

it('connects the plate error on the home page to the field', function () {
    Http::fake(['opendata.rdw.nl/*' => Http::response([])]);
    $this->from('/')->post('/kenteken', ['kenteken' => 'QQ-123-R']);

    $html = $this->get('/')->getContent();

    expect($html)->toContain('id="kenteken-fout"')->toMatch('/id="kenteken"[^>]*aria-describedby="[^"]*kenteken-fout/');
});

it('says how the plate may be typed, also when the placeholder is gone', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toMatch('/id="kenteken"[^>]*aria-describedby="[^"]*kenteken-hulp/')->toContain('id="kenteken-hulp"')->toContain('Je mag de streepjes weglaten');
});

// --- small things ---------------------------------------------------------------------------------------------

it('keeps the intro of the home page for screen readers on a small screen too', function () {
    $html = $this->get('/')->getContent();

    expect($html)->toContain('max-sm:sr-only');
    expect($html)->not->toMatch('/<p class="hero-in[^"]*\bhidden\b/');
});

it('tells that a parts link opens in a new tab, and labels the percentage on a small screen', function () {
    PartsCategory::create(['name' => 'Alles', 'keywords' => 'e', 'url' => 'https://parts.example/?auto={merk}']);
    config(['site.affiliates.parts.url' => 'https://parts.example/']);
    Cache::flush();

    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    expect($html)->toMatch('/\(partnerlink\)<span class="sr-only"> \(opent in een nieuw tabblad\)<\/span>/');
    expect($html)->toContain('<span class="sr-only">Aandeel: </span>');
});
