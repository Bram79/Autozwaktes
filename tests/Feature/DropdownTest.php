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

/** The <details> block that has this title, or null. */
function dropdown(string $html, string $title): ?string
{
    preg_match_all('#<details\b.*?</details>#s', $html, $blocks);

    return collect($blocks[0])->first(fn ($block) => str_contains($block, $title));
}

it('puts the buying checklist in a closed dropdown with the checklist items inside', function () {
    foreach (['/audi/a4/b8/2010', '/audi/a4/b8'] as $url) {
        $block = dropdown($this->get($url)->assertOk()->getContent(), 'Koopchecklist');

        expect($block)->not->toBeNull();
        expect($block)->not->toMatch('/<details[^>]* open/');                   // closed at first
        expect($block)->toContain('Let hierop bij het bekijken van een occasion')->toContain('Controleer:');
        expect(substr_count($block, 'Controleer:'))->toBe(5);
    }
});

it('uses the same dropdown for the checklist and for what the figures say', function () {
    $html = $this->get('/audi/a4/b8/2010')->getContent();

    foreach (['Koopchecklist', 'Wat de cijfers zeggen'] as $title) {
        expect(dropdown($html, $title))->toContain('<summary')->toContain('group-open:rotate-180');
    }
});

it('still shows the checklist heading to a screen reader and without JavaScript', function () {
    // a <details> works without JavaScript; the heading is inside the <summary>
    expect($this->get('/audi/a4/b8/2010')->getContent())->toMatch('#<summary[^>]*>\s*<h2 id="checklist"[^>]*>Koopchecklist</h2>#');
});
