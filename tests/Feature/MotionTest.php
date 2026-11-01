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
