<?php

use App\Models\ImportRun;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

it('is one list while it is small', function () {
    $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($xml)->toContain('<urlset')->not->toContain('<sitemapindex');
    expect($this->get('/sitemap-1.xml')->assertOk()->getContent())->toBe($xml);   // the one chunk is the same list
    $this->get('/sitemap-2.xml')->assertNotFound();
});

it('becomes an index of lists when there are more addresses than one list may hold', function () {
    config(['site.sitemap_max_urls' => 40]);

    $index = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($index)->toContain('<sitemapindex')->toContain('<loc>'.url('/sitemap-1.xml').'</loc>')->toContain('<loc>'.url('/sitemap-2.xml').'</loc>');
    preg_match_all('~<loc>'.preg_quote(url('/sitemap-'), '~').'(\d+)\.xml</loc>~', $index, $m);
    $chunks = count($m[1]);

    $all = [];
    foreach (range(1, $chunks) as $n) {
        $xml = $this->get("/sitemap-{$n}.xml")->assertOk()->getContent();
        preg_match_all('~<url><loc>(.*?)</loc>~', $xml, $urls);
        expect(count($urls[1]))->toBeLessThanOrEqual(40);
        $all = array_merge($all, $urls[1]);
    }

    expect($all)->toBe(array_values(array_unique($all)))->toContain(url('/'))->toContain(url('/audi/a4/b8'));   // nothing twice, nothing lost
    $this->get('/sitemap-'.($chunks + 1).'.xml')->assertNotFound();
});

it('has lastmod only on the data pages, in every list', function () {
    ImportRun::create(['dataset' => 'defects', 'status' => 'finished', 'started_at' => '2026-10-29 01:00:00', 'finished_at' => '2026-10-30 04:15:00']);

    $xml = $this->get('/sitemap.xml')->getContent();

    expect($xml)->toContain('<loc>'.url('/audi/a4/b8').'</loc><lastmod>2026-10-30</lastmod>');
    expect($xml)->toContain('<loc>'.route('privacy').'</loc></url>');
});

it('builds the sitemap once, even when many bots ask for it at the same time', function () {
    config(['site.sitemap_lock_wait' => 0]);
    $lock = Cache::lock('sitemap-build', 60);
    $lock->get();   // someone else is building it

    $this->get('/sitemap.xml')->assertStatus(503)->assertHeader('Retry-After', '60');

    $lock->release();
    $this->get('/sitemap.xml')->assertOk();
});

it('is built again right after the statistics are recalculated, so no bot has to wait for it', function () {
    expect(Cache::has('sitemap:meta'))->toBeFalse();

    $this->artisan('rdw:calculate --now')->assertSuccessful();

    expect(Cache::has('sitemap:meta'))->toBeTrue();
});
