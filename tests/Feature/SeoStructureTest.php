<?php

use App\Models\Generation;
use App\Models\ImportRun;
use App\Site\Seo;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

// --- titles and descriptions ---------------------------------------------------------------------------------

it('keeps a short title as it is', function () {
    expect(Seo::title('Audi A4 B8 (2010): APK-gebreken en zwakke punten'))->toBe('Audi A4 B8 (2010): APK-gebreken en zwakke punten');
});

it('shortens a title that is too long to the car name plus a short ending, never in the middle of a word', function () {
    $long = 'Mercedes-Benz E-Klasse Estate W212 Facelift (2013): APK-gebreken en zwakke punten';

    $title = Seo::title($long);

    expect(mb_strlen($title))->toBeLessThanOrEqual(60);
    expect($title)->toStartWith('Mercedes-Benz E-Klasse Estate W212 Facelift (2013)');
});

it('cuts a description that is too long at a word', function () {
    $description = Seo::description(str_repeat('woord ', 60));

    expect(mb_strlen($description))->toBeLessThanOrEqual(160);
    expect($description)->toEndWith('…');
});

it('applies the limits in the layout for every page', function () {
    $html = (string) $this->blade('<x-layouts.app title="'.str_repeat('Lange titel ', 10).': iets" description="'.str_repeat('woord ', 60).'">x</x-layouts.app>');

    preg_match('~<title>(.*?)</title>~', $html, $title);
    preg_match('~<meta name="description" content="(.*?)">~', $html, $description);

    expect(mb_strlen(html_entity_decode($title[1])))->toBeLessThanOrEqual(60);
    expect(mb_strlen(html_entity_decode($description[1])))->toBeLessThanOrEqual(160);
});

// --- headings and table --------------------------------------------------------------------------------------

it('has a heading above the defect table on the year page', function () {
    $html = $this->get('/audi/a4/b8/2010')->assertOk()->getContent();

    expect($html)->toMatch('~<h2[^>]*>[^<]*APK-gebreken[^<]*</h2>~');
    expect(strpos($html, '<h2'))->toBeLessThan(strpos($html, '<table'));
});

it('keeps the table roles explicit, because the mobile layout turns the table into blocks', function () {
    $html = $this->get('/audi/a4/b8/2010')->assertOk()->getContent();

    expect($html)->toContain('<table role="table"')->toContain('role="row"')->toContain('role="cell"')->toContain('role="rowgroup"');
});

// --- sitemap -------------------------------------------------------------------------------------------------

it('gives the data pages in the sitemap a lastmod from the last finished import, and the fixed pages none', function () {
    ImportRun::create(['dataset' => 'defects', 'status' => 'finished', 'started_at' => '2026-10-29 01:00:00', 'finished_at' => '2026-10-30 04:15:00']);

    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('<loc>'.url('/audi/a4/b8').'</loc><lastmod>2026-10-30</lastmod>');
    expect($xml)->toContain('<loc>'.route('privacy').'</loc></url>');
});

it('leaves lastmod out when nothing was imported yet', function () {
    expect($this->get('/sitemap.xml')->getContent())->not->toContain('<lastmod>');
});

// --- the catch-all generation --------------------------------------------------------------------------------

it('does not call a year "Alle bouwjaren 2010": the catch-all generation has no code worth showing', function () {
    Generation::where('slug', 'b8')->update(['code' => 'Alle bouwjaren']);

    $html = $this->get('/audi/a4/b8')->assertOk()->getContent();

    expect($html)->toContain('>Audi A4 2010</a>');
    expect($html)->not->toContain('Alle bouwjaren 2010');
    expect(Seo::generationName(Generation::where('slug', 'b8')->first()->load('carModel.brand')))->toBe('Audi A4');
});

it('still names a real generation with its code', function () {
    expect(Seo::generationName(Generation::where('slug', 'b8')->first()->load('carModel.brand')))->toBe('Audi A4 B8');
});
