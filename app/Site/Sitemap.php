<?php

namespace App\Site;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\ImportRun;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * The sitemap: every page that is worth a place in Google, split into lists of at most `site.sitemap_max_urls` addresses
 * (a sitemap file may hold 50,000), with an index when there is more than one list.
 *
 * Building it is heavy (the rankings and comparisons are calculated for all brands), so:
 * - it happens under a lock: when many bots ask at the same moment on an empty cache, one builds and the others get "try again" (503);
 * - `warm()` builds it right after the statistics are recalculated, so normally nobody has to wait.
 */
class Sitemap
{
    public function __construct(private StatsService $stats, private RankingService $rankings, private ComparisonService $comparisons) {}

    /** The index, or the one list when there is only one. */
    public function index(): string
    {
        $meta = $this->meta();

        return $meta['chunks'] === 1 ? $this->chunk(1) : view('sitemap-index', ['chunks' => range(1, $meta['chunks']), 'lastmod' => $meta['lastmod']])->render();
    }

    /** One list, or null when there is no such list. */
    public function chunk(int $number): ?string
    {
        $meta = $this->meta();

        return $number >= 1 && $number <= $meta['chunks'] ? Cache::get("sitemap:chunk:{$number}") : null;
    }

    /** Build everything again now (after a recalculation). */
    public function warm(): void
    {
        Cache::forget('sitemap:meta');
        $this->meta();
    }

    /** @return array{chunks: int, lastmod: string|null} */
    private function meta(): array
    {
        $meta = Cache::get('sitemap:meta');

        if ($meta !== null) {
            return $meta;
        }

        $lock = Cache::lock('sitemap-build', 600);

        try {
            $lock->block((int) config('site.sitemap_lock_wait', 5));
        } catch (LockTimeoutException) {
            throw new SitemapBusyException;
        }

        try {
            // someone else may have finished while we waited for the lock
            return Cache::get('sitemap:meta') ?? $this->build();
        } finally {
            $lock->release();
        }
    }

    /** @return array{chunks: int, lastmod: string|null} */
    private function build(): array
    {
        // the data only changes when an import finishes, so that is the honest "last modified" of the data pages
        $lastImport = ImportRun::where('status', 'finished')->latest('finished_at')->value('finished_at');
        $lastmod = $lastImport ? Carbon::parse($lastImport)->toDateString() : null;
        $fixed = [route('privacy'), route('apk')];

        $chunks = array_chunk($this->urls(), max(1, (int) config('site.sitemap_max_urls')));

        foreach ($chunks as $i => $urls) {
            Cache::forever('sitemap:chunk:'.($i + 1), view('sitemap', ['urls' => $urls, 'lastmod' => $lastmod, 'fixed' => $fixed])->render());
        }

        $meta = ['chunks' => count($chunks), 'lastmod' => $lastmod];
        Cache::forever('sitemap:meta', $meta);

        return $meta;
    }

    /** @return string[] */
    private function urls(): array
    {
        $urls = [url('/'), route('privacy'), route('apk')];

        // only brands that have a page worth indexing: the brand page is noindex without models with numbers
        foreach (Brand::withStats()->orderBy('slug')->get() as $brand) {
            $urls[] = route('brand', $brand);
        }

        foreach (CarModel::with(['brand', 'generations'])->get() as $model) {
            $generations = $this->stats->generationsWithData($model);
            if ($generations->isEmpty()) {
                continue;
            }
            // with one generation the model page is the same search as that generation's page (its canonical points there)
            if ($generations->count() > 1) {
                $urls[] = route('model', [$model->brand, $model->slug]);
            }

            foreach ($generations as $generation) {
                $urls[] = route('generation', [$model->brand, $model->slug, $generation->slug]);
                // a build year that has had no APK yet has no numbers: not worth a place in the index
                foreach (array_keys($this->stats->years($generation)) as $year) {
                    if ($year <= RankingService::lastInspectedYear()) {
                        $urls[] = route('year', [$model->brand, $model->slug, $generation->slug, $year]);
                    }
                }
            }
        }

        // rankings with enough rows to be worth indexing, and the comparison pages that exist
        $urls[] = route('rankings');
        $urls[] = route('ranking', ['alles']);
        foreach (Brand::orderBy('slug')->get() as $brand) {
            if (count($this->rankings->scores($brand->id)) >= 5) {
                $urls[] = route('ranking', ['merk', $brand->slug]);
            }
        }
        foreach ($this->rankings->fuels() as $slug => $fuel) {
            if (count($this->rankings->scores(null, $fuel)) >= 5) {
                $urls[] = route('ranking', ['brandstof', $slug]);
            }
        }
        foreach ($this->rankings->years() as $year) {
            if (count($this->rankings->scores(null, null, $year)) >= 5) {
                $urls[] = route('ranking', ['bouwjaar', $year]);
            }
        }
        foreach ($this->comparisons->pairs() as $pair) {
            $urls[] = route('comparison', ComparisonService::slug(...$pair));
        }

        return $urls;
    }
}
