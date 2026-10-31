<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Generation;
use App\Models\ImportRun;
use App\Site\ComparisonService;
use App\Site\RankingService;
use App\Site\StatsService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PageController extends Controller
{
    public function __construct(private StatsService $stats) {}

    public function home()
    {
        return view('pages.home', ['popular' => $this->stats->popularModels(12)]);
    }

    public function privacy()
    {
        return view('pages.privacy');
    }

    public function brand(string $brand)
    {
        $brand = Brand::where('slug', $brand)->firstOrFail();
        $models = $brand->carModels()->withStats()->orderBy('name')->get();

        return view('pages.brand', compact('brand', 'models'));
    }

    public function model(string $brand, string $model)
    {
        $model = $this->findModel($brand, $model);
        $generations = $this->stats->generationsWithData($model);
        $similar = $model->brand->carModels()->where('id', '!=', $model->id)->orderBy('name')->get();

        return view('pages.model', compact('model', 'generations', 'similar'));
    }

    public function generation(string $brand, string $model, string $generation)
    {
        $generation = $this->findGeneration($brand, $model, $generation);

        return view('pages.generation', [
            'generation' => $generation,
            'years' => $this->stats->years($generation),
            'siblings' => $this->stats->generationsWithData($generation->carModel)->where('id', '!=', $generation->id),
            'similar' => $generation->carModel->brand->carModels()->where('id', '!=', $generation->car_model_id)->orderBy('name')->get(),
        ]);
    }

    public function year(string $brand, string $model, string $generation, int $year)
    {
        $generation = $this->findGeneration($brand, $model, $generation);
        $years = $this->stats->years($generation);

        abort_unless(isset($years[$year]), 404);

        return view('pages.year', [
            'generation' => $generation,
            'year' => $year,
            'years' => $years,
            'result' => $this->stats->topDefects($generation, $year),
            'fuels' => $this->stats->fuels($generation),
        ]);
    }

    public function sitemap()
    {
        $xml = Cache::rememberForever('sitemap', function () {
            $urls = [url('/'), route('privacy'), route('apk')];

            // only brands that have a page worth indexing: the brand page is noindex without models with numbers
            foreach (Brand::withStats()->orderBy('slug')->get() as $brand) {
                $urls[] = route('brand', $brand);
            }

            $models = CarModel::with(['brand', 'generations'])->get();
            foreach ($models as $model) {
                $generations = $this->stats->generationsWithData($model);
                if ($generations->isEmpty()) {
                    continue;
                }
                $urls[] = route('model', [$model->brand, $model->slug]);

                foreach ($generations as $generation) {
                    $urls[] = route('generation', [$model->brand, $model->slug, $generation->slug]);
                    // a build year that has had no APK yet has no numbers: not worth a place in the index
                    foreach (array_keys($this->stats->years($generation)) as $year) {
                        if ($year > RankingService::lastInspectedYear()) {
                            continue;
                        }
                        $urls[] = route('year', [$model->brand, $model->slug, $generation->slug, $year]);
                    }
                }
            }

            // rankings with enough rows to be worth indexing, and the comparison pages that exist
            $rankings = app(RankingService::class);
            $urls[] = route('rankings');
            $urls[] = route('ranking', ['alles']);
            foreach (Brand::orderBy('slug')->get() as $brand) {
                if (count($rankings->scores($brand->id)) >= 5) {
                    $urls[] = route('ranking', ['merk', $brand->slug]);
                }
            }
            foreach ($rankings->fuels() as $slug => $fuel) {
                if (count($rankings->scores(null, $fuel)) >= 5) {
                    $urls[] = route('ranking', ['brandstof', $slug]);
                }
            }
            foreach ($rankings->years() as $year) {
                if (count($rankings->scores(null, null, $year)) >= 5) {
                    $urls[] = route('ranking', ['bouwjaar', $year]);
                }
            }
            foreach (app(ComparisonService::class)->pairs() as $pair) {
                $urls[] = route('comparison', ComparisonService::slug(...$pair));
            }

            // the data only changes when an import finishes, so that is the honest "last modified" of the data pages
            $lastImport = ImportRun::where('status', 'finished')->latest('finished_at')->value('finished_at');

            return view('sitemap', [
                'urls' => $urls,
                'lastmod' => $lastImport ? Carbon::parse($lastImport)->toDateString() : null,
                'fixed' => [route('privacy'), route('apk')],
            ])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots()
    {
        $text = "User-agent: *\nAllow: /\nDisallow: /go/\nDisallow: /apk-herinnering/\nDisallow: /admin/\nDisallow: /livewire\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($text, 200, ['Content-Type' => 'text/plain']);
    }

    private function findModel(string $brandSlug, string $modelSlug): CarModel
    {
        $brand = Brand::where('slug', $brandSlug)->firstOrFail();

        return $brand->carModels()->where('slug', $modelSlug)->firstOrFail()->setRelation('brand', $brand);
    }

    private function findGeneration(string $brandSlug, string $modelSlug, string $generationSlug): Generation
    {
        $model = $this->findModel($brandSlug, $modelSlug);
        $generation = $model->generations()->where('slug', $generationSlug)->firstOrFail();

        // a generation without statistics is not a page worth showing
        abort_if($this->stats->generationsWithData($model)->doesntContain('id', $generation->id), 404);

        return $generation->setRelation('carModel', $model);
    }
}
