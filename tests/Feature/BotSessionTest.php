<?php

use App\Support\Bots;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    config(['session.driver' => 'database']);
});

it('knows search engines, link previews, scripts and monitors', function (string $agent, bool $isBot) {
    expect(Bots::isBot($agent))->toBe($isBot);
})->with([
    ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', true],
    ['Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; bingbot/2.0)', true],
    ['facebookexternalhit/1.1', true],
    ['curl/8.4.0', true],
    ['python-requests/2.31', true],
    ['', true],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15', false],
    ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile/15E148', false],
]);

it('does not make a session row for a search engine that reads a page', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->get('/audi/a4/b8')->assertOk();

    expect(DB::table('sessions')->count())->toBe(0);
});

it('still makes a session for a visitor, who needs it for the forms and the search', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Safari/605.1')->get('/audi/a4/b8')->assertOk();

    expect(DB::table('sessions')->count())->toBe(1);
});

it('answers a bot that posts the plate form like anyone else', function () {
    $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1)')->post('/kenteken', ['kenteken' => 'AB-123-C'])->assertRedirect();
});

it('lets the browser keep the files in /build for a year, because their names change with their content', function () {
    $htaccess = file_get_contents(public_path('.htaccess'));

    expect($htaccess)->toContain('/build/')->toContain('max-age=31536000')->toContain('immutable');
    expect(file_get_contents(base_path('docs/LIVEGANG.md')))->toContain('gzip')->toContain('/build/');
});
