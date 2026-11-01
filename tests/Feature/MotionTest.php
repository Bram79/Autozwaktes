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

it('puts the real percentage in the page, so it is right without JavaScript too', function () {
    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    preg_match_all('/data-count="([\d.]+)">([^<]+)<\/span>/', $html, $matches, PREG_SET_ORDER);

    expect($matches)->not->toBeEmpty();
    foreach ($matches as [, $value, $text]) {
        expect($text)->toBe(number_format((float) $value, 1, ',', '.').'%');
    }
});

it('does not animate for visitors who asked for less motion', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $reduced = substr($css, strpos($css, '@media (prefers-reduced-motion: reduce)'));

    expect($reduced)->toContain('filter: none');
    expect(file_get_contents(resource_path('js/app.js')))->toContain("matchMedia('(prefers-reduced-motion: reduce)')");
});

it('always ends the count-up on the exact text the server wrote', function () {
    expect(file_get_contents(resource_path('js/app.js')))->toContain('t < 1 ? format.format(target * eased) : finalText');
});

// --- every page -------------------------------------------------------------------------------------------------

it('lets the content of every page come in, and switches that off for reduced motion', function () {
    $css = file_get_contents(resource_path('css/app.css'));
    $reduced = substr($css, strpos($css, '@media (prefers-reduced-motion: reduce)'));

    expect($css)->toContain('#inhoud > *:not(.reveal):not(:has(.reveal))')->toContain('@view-transition');
    expect($reduced)->toContain('#inhoud > *')->toContain('#consent-banner')->toContain('navigation: none');
});

it('marks blocks below the fold for the scroll animation, but not for reduced motion', function () {
    $js = file_get_contents(resource_path('js/app.js'));

    expect($js)->toContain('function markBelowTheFold')->toContain("'#inhoud > *:not(.reveal)'");
    expect($js)->toMatch("/matchMedia\\('\\(prefers-reduced-motion: reduce\\)'\\)\\.matches\\) \\{\\s+markBelowTheFold/");
});

it('counts up the new price of a looked-up car, with the real price in the page', function () {
    $html = view('components.plate-price', ['price' => 23450])->render();

    expect($html)->toContain('data-count="23450">€ 23.450</span>');
});
