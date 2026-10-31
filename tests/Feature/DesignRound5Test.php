<?php

use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

/** The Blade files and the CSS that the public site is made of (not the admin, not the standard Laravel page). */
function publicViewFiles(): array
{
    $files = [resource_path('css/app.css')];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'), FilesystemIterator::SKIP_DOTS)) as $file) {
        $path = $file->getPathname();
        if (str_ends_with($path, '.blade.php') && ! str_contains($path, '/views/filament/') && ! str_ends_with($path, '/welcome.blade.php')) {
            $files[] = $path;
        }
    }

    return $files;
}

// --- the strip at the top is gone --------------------------------------------------------------------------------------

it('has no moving strip of text at the top of the page', function () {
    $this->get('/')->assertOk()->assertDontSee('animate-marquee', false)->assertDontSee('Echte APK-data van de RDW');

    expect(file_get_contents(resource_path('css/app.css')))->not->toContain('marquee');
});

it('still says in the footer that the site is independent and uses open RDW data', function () {
    $this->get('/')->assertSee('Nuvik is een onafhankelijke website', false)->assertSee('Bron: RDW open data', false);
});

// --- one calm look -------------------------------------------------------------------------------------------------------

it('does not use the loud styles that make a site look like a template', function (string $pattern, string $why) {
    $found = [];
    foreach (publicViewFiles() as $file) {
        if (preg_match($pattern, file_get_contents($file))) {
            $found[] = str_replace(base_path().'/', '', $file);
        }
    }

    expect($found)->toBe([], $why);
})->with([
    'extra bold' => ['/font-extrabold/', 'headings are semibold'],
    'big round corners' => ['/rounded-(2xl|xl)\b/', 'surfaces use one corner size (rounded-lg), pills are for buttons and tags'],
    'drop shadows' => ['/(?<![-\w])(hover:|focus:)?shadow-(sm|md|lg|xl|2xl)\b/', 'lines instead of shadows'],
]);

it('writes the headline of the home page in white, so that yellow is only for the actions', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toMatch('/<h1[^>]*text-white/')->not->toMatch('/<h1[^>]*text-accent-300/');
});

it('has no gradient or glow in the hero', function () {
    $this->get('/')->assertDontSee('hero-backdrop', false);
    expect(file_get_contents(resource_path('css/app.css')))->not->toContain('radial-gradient');
});

it('uses the same box for every partner block, and does not repeat its classes', function () {
    config(['site.affiliates.insurance.url' => 'https://partner.example/']);
    Cache::flush();

    $this->get('/audi/a4/b8')->assertSee('class="ad-card', false);
    expect(file_get_contents(resource_path('css/app.css')))->toContain('.ad-card');
});

it('draws the bars of the defect table thin', function () {
    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    expect($html)->not->toContain('bar h-3 rounded-full')->toContain('bar h-1.5');
});
