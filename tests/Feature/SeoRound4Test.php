<?php

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Generation;
use App\Site\StatsService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    Carbon::setTestNow('2026-10-31');
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

afterEach(fn () => Carbon::setTestNow());

/** All JSON-LD blocks of a page, decoded. */
function jsonLd(string $html): array
{
    preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);

    return array_map(fn ($json) => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
}

function blockOfType(string $html, string $type): ?array
{
    return collect(jsonLd($html))->first(fn ($block) => ($block['@type'] ?? null) === $type);
}

// --- search pages and links to pages without numbers ---------------------------------------------------------------

it('keeps the search results page out of the index, but not the home page', function () {
    $this->get('/?q=golf')->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false);
    $this->get('/')->assertOk()->assertDontSee('noindex', false);
});

it('only links to other models that have numbers', function () {
    $audi = Brand::where('slug', 'audi')->first();
    CarModel::create(['brand_id' => $audi->id, 'name' => 'Q99', 'slug' => 'q99']);   // no statistics: its page is noindex
    Cache::flush();

    $this->get('/audi/a4')->assertOk()->assertDontSee('/audi/q99', false);
    $this->get('/audi/a4/b8')->assertOk()->assertDontSee('/audi/q99', false);
});

// --- two pages for the same search -----------------------------------------------------------------------------------

it('gives the model page of a model with one generation the address of that generation as canonical, and leaves it out of the sitemap', function () {
    $model = CarModel::where('slug', 'a4')->first();
    $keep = $model->generations()->orderBy('year_from')->first();
    $model->generations()->where('id', '!=', $keep->id)->delete();
    Cache::flush();

    $html = $this->get('/audi/a4')->assertOk()->getContent();
    $sitemap = $this->get('/sitemap.xml')->getContent();

    expect($html)->toContain('<link rel="canonical" href="'.url("/audi/a4/{$keep->slug}").'"');
    expect($sitemap)->not->toContain('<loc>'.url('/audi/a4').'</loc>')->toContain('<loc>'.url("/audi/a4/{$keep->slug}").'</loc>');
});

it('keeps the model page of a model with several generations as it was', function () {
    $html = $this->get('/volkswagen/golf')->assertOk()->getContent();

    expect(CarModel::where('slug', 'golf')->first()->generations()->count())->toBeGreaterThan(1);
    expect($html)->toContain('<link rel="canonical" href="'.url('/volkswagen/golf').'"');
    expect($this->get('/sitemap.xml')->getContent())->toContain('<loc>'.url('/volkswagen/golf').'</loc>');
});

// --- structured data ------------------------------------------------------------------------------------------------

it('tells Google the name of the site and how its search works', function () {
    $html = $this->get('/')->assertOk()->getContent();

    $site = blockOfType($html, 'WebSite');
    expect($site['name'])->toBe('Nuvik')->and($site['url'])->toBe(url('/'));
    expect($site['potentialAction']['@type'])->toBe('SearchAction');
    expect($site['potentialAction']['target'])->toBe(url('/').'?q={search_term_string}');
    expect($site['potentialAction']['query-input'])->toBe('required name=search_term_string');

    $organization = blockOfType($html, 'Organization');
    expect($organization['name'])->toBe('Nuvik')->and($organization['url'])->toBe(url('/'));
});

it('describes the numbers of a generation and of a build year as a dataset, without a made-up period', function () {
    $generation = $this->get('/audi/a4/b8')->assertOk()->getContent();
    $year = $this->get('/audi/a4/b8/2010')->assertOk()->getContent();

    foreach ([$generation, $year] as $html) {
        $dataset = blockOfType($html, 'Dataset');
        expect($dataset['creator']['name'])->toBe('Nuvik');
        expect($dataset['keywords'])->toContain('APK')->toContain('Audi A4');
        expect($dataset)->not->toHaveKey('temporalCoverage');   // we do not know that from the data
    }

    expect(blockOfType($year, 'Dataset')['name'])->toContain('2010');
    expect(blockOfType($year, 'Dataset')['url'])->toBe(url('/audi/a4/b8/2010'));
});

it('gives a ranking an ItemList with the top ten, linking to the generation pages', function () {
    $html = $this->get('/ranglijst/alles')->assertOk()->getContent();

    $list = blockOfType($html, 'ItemList');
    expect($list['itemListElement'])->not->toBeEmpty()->and(count($list['itemListElement']))->toBeLessThanOrEqual(10);
    expect($list['itemListElement'][0]['position'])->toBe(1);
    expect($list['itemListElement'][0]['url'])->toStartWith(url('/'))->toMatch('~/[a-z0-9-]+/[a-z0-9-]+/[a-z0-9-]+$~');
    expect($list['numberOfItems'])->toBe(count($list['itemListElement']));
});

// --- descriptions with numbers --------------------------------------------------------------------------------------

it('puts the numbers of the year in the description of a year page, within the limit', function () {
    $html = $this->get('/audi/a4/b8/2010')->assertOk()->getContent();
    preg_match('~<meta name="description" content="(.*?)">~', $html, $m);
    $description = html_entity_decode($m[1]);

    $result = app(StatsService::class)->topDefects(Generation::where('slug', 'b8')->first(), 2010);

    expect(mb_strlen($description))->toBeLessThanOrEqual(160);
    expect($description)->toContain(number_format($result['total'], 0, ',', '.').' voertuigen');
    expect($description)->toContain(number_format($result['defects']->first()->percentage, 1, ',', '.').'%');
});

it('keeps the plain description for a year without numbers', function () {
    $this->get('/volkswagen/golf/mk8')->assertOk();
    DB::table('vehicle_counts')->insert(['car_model_id' => CarModel::where('slug', 'golf')->value('id'), 'build_year' => 2025, 'fuel' => 'Benzine', 'total_vehicles' => 5000]);
    Cache::flush();

    $html = $this->get('/volkswagen/golf/mk8/2025')->assertOk()->getContent();

    expect($html)->toContain('De meest voorkomende APK-gebreken')->not->toContain('voertuigen. Met koopchecklist');
});

it('says in the description of a model page how many generations it has and from which years', function () {
    $html = $this->get('/volkswagen/golf')->assertOk()->getContent();
    preg_match('~<meta name="description" content="(.*?)">~', $html, $m);

    expect($m[1])->toMatch('/\d+ generaties/')->toMatch('/\d{4}/');
});
