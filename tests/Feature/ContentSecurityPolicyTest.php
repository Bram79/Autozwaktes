<?php

use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
});

it('sends a content security policy on public pages, with a fresh nonce for scripts', function () {
    $first = $this->get('/')->assertOk()->headers->get('Content-Security-Policy');
    $second = $this->get('/')->headers->get('Content-Security-Policy');

    expect($first)->toContain("default-src 'self'")->toContain("object-src 'none'")->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")->toContain("frame-ancestors 'self'")->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9+\/=]{16,}'/");
    expect($first)->not->toBe($second);   // another nonce for every request
});

it('puts the same nonce on the scripts that Livewire adds to the page', function () {
    $response = $this->get('/')->assertOk();
    preg_match("/'nonce-([^']+)'/", $response->headers->get('Content-Security-Policy'), $nonce);

    expect($response->getContent())->toContain('nonce="'.$nonce[1].'"');
});

it('can run in report-only mode first', function () {
    config(['site.csp' => 'report-only']);

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
    expect($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("default-src 'self'");
});

it('can be switched off', function () {
    config(['site.csp' => 'off']);

    expect($this->get('/')->headers->has('Content-Security-Policy'))->toBeFalse();
});

it('does not limit where scripts and images come from while advertisements are on, but keeps the safe parts', function () {
    config(['site.ads.enabled' => true, 'site.ads.script_url' => 'https://ads.example/ads.js']);

    $policy = $this->get('/')->headers->get('Content-Security-Policy');

    expect($policy)->not->toContain('script-src')->not->toContain('default-src')
        ->toContain("object-src 'none'")->toContain("frame-ancestors 'self'");
});

it('leaves the admin panel alone, because Filament needs inline scripts', function () {
    expect($this->get('/admin/login')->headers->has('Content-Security-Policy'))->toBeFalse();
});
