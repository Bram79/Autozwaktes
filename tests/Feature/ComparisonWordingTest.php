<?php

use App\Models\Generation;
use App\Site\AdviceWriter;
use App\Site\StatsService;
use Database\Seeders\DemoStatsSeeder;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(StarterModelsSeeder::class);
    $this->seed(DemoStatsSeeder::class);
    $this->generation = Generation::where('slug', 'b8')->first();
});

/** Defect $code: the other models have it in $others % of their cars, this generation in $own %. */
function setDefectRates(Generation $generation, string $code, float $others, float $own): void
{
    DB::table('defect_stats')->where('car_model_id', '!=', $generation->car_model_id)->where('defect_code', $code)
        ->update(['vehicles_with_defect' => DB::raw("floor(total_vehicles * {$others} / 100)")]);
    DB::table('defect_stats')->where('generation_id', $generation->id)->where('defect_code', $code)
        ->update(['vehicles_with_defect' => DB::raw("floor(total_vehicles * {$own} / 100)")]);
    Cache::flush();
}

it('also finds a rare defect that is many times more common in this model', function () {
    setDefectRates($this->generation, '110', others: 1.0, own: 7.0);

    $weak = app(StatsService::class)->comparison($this->generation)['weak'];

    expect(collect($weak)->pluck('code')->all())->toContain('110');
});

it('takes the limits for "more often" and "less often" from the config, for the summary and the table alike', function () {
    setDefectRates($this->generation, '110', others: 10.0, own: 20.0);   // twice as often

    config(['site.compare.ratio_high' => 3.0]);
    Cache::flush();
    expect(collect(app(StatsService::class)->comparison($this->generation)['weak'])->pluck('code')->all())->not->toContain('110');
    expect($this->get('/audi/a4/b8')->getContent())->not->toContain('text-red-800">vaker dan gemiddeld');

    config(['site.compare.ratio_high' => 1.5]);
    Cache::flush();
    expect(collect(app(StatsService::class)->comparison($this->generation)['weak'])->pluck('code')->all())->toContain('110');
    expect($this->get('/audi/a4/b8')->getContent())->toContain('text-red-800">vaker dan gemiddeld');
});

it('does not call "less often" better, and says what it does not mean', function () {
    setDefectRates($this->generation, '110', others: 10.0, own: 2.0);

    $rows = collect(app(AdviceWriter::class)->rows($this->generation));
    $row = $rows->firstWhere('label', 'Minder vaak dan gemiddeld');

    expect($rows->pluck('label')->all())->not->toContain('Beter dan gemiddeld');
    expect(implode(' ', $row['lines']))->toContain('Dit zegt niet dat de auto op die onderdelen beter is gemaakt');
});

it('says "keer zo vaak", which is the same as the ratio, and not "keer vaker"', function () {
    setDefectRates($this->generation, '110', others: 10.0, own: 20.0);

    $row = collect(app(AdviceWriter::class)->rows($this->generation))->firstWhere('label', 'Vaker dan gemiddeld');

    expect(implode(' ', $row['lines']))->toContain('keer zo vaak')->not->toContain('keer vaker');
});
