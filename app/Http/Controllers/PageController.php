<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\CarModel;
use App\Models\Generation;
use App\Site\Sitemap;
use App\Site\SitemapBusyException;
use App\Site\StatsService;
use Closure;

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

    /** /sitemap.xml: the list, or the index of lists. */
    public function sitemap(Sitemap $sitemap)
    {
        return $this->xml(fn () => $sitemap->index());
    }

    /** /sitemap-1.xml, /sitemap-2.xml, ... */
    public function sitemapChunk(Sitemap $sitemap, int $chunk)
    {
        return $this->xml(fn () => $sitemap->chunk($chunk) ?? abort(404));
    }

    private function xml(Closure $build)
    {
        try {
            return response($build(), 200, ['Content-Type' => 'application/xml']);
        } catch (SitemapBusyException) {
            // someone else is building it: bots come back later
            return response('', 503, ['Retry-After' => '60']);
        }
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
