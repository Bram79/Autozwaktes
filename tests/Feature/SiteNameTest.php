<?php

use App\Mail\ApkReminderMail;
use App\Mail\ConfirmApkReminder;
use App\Models\ApkReminder;
use App\Models\User;
use App\Site\Brand;
use App\Site\Schema;
use App\Site\Seo;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    Cache::flush();
});

it('is called Nuvik', function () {
    expect(config('site.name'))->toBe('Nuvik');
    $this->get('/')->assertOk()->assertSee('Nuvik');
});

it('takes the name from one setting everywhere, so that renaming the site is one line in .env', function () {
    config(['site.name' => 'Voorbeeldmerk']);
    Carbon::setTestNow('2026-10-31');
    $reminder = ApkReminder::create(['email' => 'a@example.com', 'license_plate' => 'AB123C', 'token' => 't1', 'confirmed_at' => now(), 'apk_expires_at' => now()->addDays(10)]);
    Route::get('/_niet-gevonden', fn () => abort(404))->middleware('web');

    $pages = [
        '/' => $this->get('/')->getContent(),
        '/privacy' => $this->get('/privacy')->getContent(),
        '/ranglijsten' => $this->get('/ranglijsten')->getContent(),
        '/ranglijst/alles' => $this->get('/ranglijst/alles')->getContent(),
        '/apk-herinnering' => $this->get('/apk-herinnering')->getContent(),
        '404' => $this->get('/_niet-gevonden')->getContent(),
        '500' => view('errors.500')->render(),
        '503' => view('errors.503')->render(),
        'mail bevestigen' => (new ConfirmApkReminder($reminder))->render(),
        'mail herinnering' => (new ApkReminderMail($reminder))->render(),
    ];
    Carbon::setTestNow();

    $problems = [];
    foreach ($pages as $where => $html) {
        str_contains($html, 'Voorbeeldmerk') || $problems[] = "{$where} does not use the name from the setting";
        str_contains($html, 'Auto Zwaktes') && $problems[] = "{$where} still has the old name";
    }
    expect($problems)->toBe([]);

    expect(Schema::website()['name'])->toBe('Voorbeeldmerk')->and(Schema::organization()['name'])->toBe('Voorbeeldmerk');
    expect(Seo::title('Een erg lange titel over iets met heel veel woorden erin: ranglijst | Voorbeeldmerk'))->not->toContain('Voorbeeldmerk');
});

it('has the name in the browser title of the pages that carry it', function () {
    config(['site.name' => 'Voorbeeldmerk']);

    $missing = [];
    foreach (['/privacy'] as $url) {
        preg_match('~<title>(.*?)</title>~', $this->get($url)->getContent(), $m);
        str_ends_with(html_entity_decode($m[1] ?? ''), '| Voorbeeldmerk') || $missing[] = $url.': '.($m[1] ?? 'no title');
    }
    expect($missing)->toBe([]);
    expect($this->get('/')->getContent())->toMatch('~<title>Voorbeeldmerk: [^<]*</title>~');
});

it('has no old name left in the code, the views or the settings', function () {
    $found = [];
    foreach (['app', 'config', 'resources', 'routes', 'database', 'lang'] as $dir) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path($dir), FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && preg_match('/Auto ?Zwaktes/i', (string) file_get_contents($file->getPathname()))) {
                $found[] = str_replace(base_path().'/', '', $file->getPathname());
            }
        }
    }

    // the GitHub address in the Wikimedia user agent is the name of the repository: that keeps its name until the repository is renamed
    expect(array_values(array_diff($found, ['app/Wikidata/WikidataClient.php'])))->toBe([]);
});

it('shows the admin under the same name', function () {
    config(['site.name' => 'Voorbeeldmerk']);

    $this->get('/admin/login')->assertOk()->assertSee('Voorbeeldmerk beheer');
});

// --- the logo ------------------------------------------------------------------------------------------------------

/** Points the app at an empty public folder, so these tests never depend on the real logo in public/images. */
function useEmptyPublicFolder(): string
{
    $folder = sys_get_temp_dir().'/nuvik-public-'.uniqid();
    mkdir($folder.'/images', 0755, true);
    app()->usePublicPath($folder);

    return $folder;
}

it('shows the name as text with the yellow dot while there is no logo file', function () {
    useEmptyPublicFolder();

    expect(Brand::logoUrl())->toBeNull();

    $this->get('/')->assertOk()->assertDontSee('images/logo', false)->assertSee('size-2.5 rounded-full bg-accent-400', false)->assertSee(config('site.name'));
});

it('shows your own logo next to the name in the header when public/images/logo.svg exists', function () {
    $folder = useEmptyPublicFolder();
    file_put_contents($folder.'/images/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"/></svg>');

    $html = $this->get('/')->assertOk()->getContent();

    expect(Brand::logoUrl())->toEndWith('/images/logo.svg');
    expect($html)->toContain('<img src="'.Brand::logoUrl().'"')->not->toContain('size-2.5 rounded-full bg-accent-400');
    $this->get('/')->assertSee(config('site.name'));
});

it('uses logo.png when there is no logo.svg', function () {
    $folder = useEmptyPublicFolder();
    file_put_contents($folder.'/images/logo.png', 'png');

    expect(Brand::logoUrl())->toEndWith('/images/logo.png');
});

it('has a real favicon, linked from the site and from the admin', function () {
    expect(filesize(public_path('favicon.ico')))->toBeGreaterThan(0);
    expect(filesize(public_path('apple-touch-icon.png')))->toBeGreaterThan(0);

    $this->get('/')->assertOk()->assertSee('rel="icon" href="'.asset('favicon.ico').'"', false)->assertSee('apple-touch-icon', false);
    $this->get('/admin/login')->assertOk()->assertSee(asset('favicon.ico'), false);
});

it('explains in the go-live notes where the name and the logo are set', function () {
    expect(file_get_contents(base_path('docs/LIVEGANG.md')))->toContain('APP_NAME')->toContain('public/images/logo.svg')->toContain('nuvik.nl');
});
