<?php

use App\Models\Brand;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
    RateLimiter::clear('plate:127.0.0.1');
    config(['site.affiliates.vehicle_report.url' => 'https://partner.example/rapport?auto={merk}+{model}&jaar={jaar}']);
});

// --- K1: breadcrumb structured data ------------------------------------------------------------------------------

it('writes valid breadcrumb JSON-LD with a real @context', function () {
    $html = $this->get('/audi/a4')->assertOk()->getContent();

    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
    $crumbs = collect($matches[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'BreadcrumbList');

    expect($html)->not->toContain('contextArgs');
    expect($crumbs)->not->toBeNull()->and($crumbs['@context'])->toBe('https://schema.org');
    expect($crumbs['itemListElement'][0]['name'])->toBe('Home');
});

// --- K2: sitemap ----------------------------------------------------------------------------------------------------

it('only lists brands in the sitemap that have models with numbers', function () {
    Brand::create(['name' => 'BMW', 'slug' => 'bmw', 'rdw_name' => 'BMW']);   // new brand, nothing imported yet

    $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($sitemap)->toContain(url('/audi').'<')->not->toContain(url('/bmw').'<');
});
