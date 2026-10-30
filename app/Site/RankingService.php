<?php

namespace App\Site;

use App\Models\Generation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rankings of generations by how many APK defects they have.
 *
 * The score is "defects per 100 cars": all defect findings of the generation (each car counted once per defect)
 * divided by the number of cars. A lower score is better. Only generations with enough cars are ranked.
 */
class RankingService
{
    /**
     * All ranked generations for a scope, best (lowest score) first.
     *
     * @return array<int, array{brand: string, brand_slug: string, model: string, model_slug: string, code: string, generation_slug: string, years: string, total: int, score: float}>
     */
    public function scores(?int $brandId = null, ?string $fuel = null, ?int $year = null): array
    {
        return Cache::rememberForever("ranking:{$brandId}:{$fuel}:{$year}", fn () => $this->calculate($brandId, $fuel, $year));
    }

    /** Fuels with enough cars for a ranking: [slug => name]. */
    public function fuels(): array
    {
        return Cache::rememberForever('ranking:fuels', fn () => DB::table('vehicle_counts')
            ->groupBy('fuel')->havingRaw('sum(total_vehicles) >= ?', [config('site.min_ranking_sample') * 4])
            ->orderByDesc(DB::raw('sum(total_vehicles)'))->pluck('fuel')
            ->mapWithKeys(fn ($fuel) => [Str::slug($fuel) => $fuel])->all());
    }

    /** Build years with enough cars for a ranking, newest first. */
    public function years(): array
    {
        return Cache::rememberForever('ranking:years', fn () => DB::table('vehicle_counts')
            ->groupBy('build_year')->havingRaw('sum(total_vehicles) >= ?', [config('site.min_ranking_sample') * 4])
            ->orderByDesc('build_year')->pluck('build_year')->map(fn ($y) => (int) $y)->all());
    }

    private function calculate(?int $brandId, ?string $fuel, ?int $year): array
    {
        $totals = DB::table('vehicle_counts as vc')
            ->join('generations as g', function ($join) {
                $join->on('g.car_model_id', '=', 'vc.car_model_id')->on('vc.build_year', '>=', 'g.year_from');
            })
            ->whereRaw('vc.build_year <= coalesce(g.year_to, 9999)')
            ->join('car_models as m', 'm.id', '=', 'vc.car_model_id')
            ->when($brandId, fn ($q) => $q->where('m.brand_id', $brandId))
            ->when($fuel, fn ($q) => $q->where('vc.fuel', $fuel))
            ->when($year, fn ($q) => $q->where('vc.build_year', $year))
            ->groupBy('g.id')
            ->selectRaw('g.id as id, sum(vc.total_vehicles) as total')
            ->pluck('total', 'id');

        $defects = DB::table('defect_stats as ds')
            ->join('car_models as m', 'm.id', '=', 'ds.car_model_id')
            ->whereNotNull('ds.generation_id')
            ->when($brandId, fn ($q) => $q->where('m.brand_id', $brandId))
            ->when($fuel, fn ($q) => $q->where('ds.fuel', $fuel))
            ->when($year, fn ($q) => $q->where('ds.build_year', $year))
            ->groupBy('ds.generation_id')
            ->selectRaw('ds.generation_id as id, sum(ds.vehicles_with_defect) as defects')
            ->pluck('defects', 'id');

        $ranked = $totals->filter(fn ($total, $id) => $total >= config('site.min_ranking_sample') && $defects->has($id));

        return Generation::with('carModel.brand')->whereIn('id', $ranked->keys())->get()
            ->map(fn (Generation $g) => [
                'brand' => $g->carModel->brand->name,
                'brand_slug' => $g->carModel->brand->slug,
                'model' => $g->carModel->name,
                'model_slug' => $g->carModel->slug,
                'code' => $g->code,
                'generation_slug' => $g->slug,
                'years' => Seo::yearsLabel($g),
                'total' => (int) $ranked[$g->id],
                'score' => round($defects[$g->id] / $ranked[$g->id] * 100, 1),
            ])
            ->sortBy('score')->values()->all();
    }
}
