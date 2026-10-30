<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\CarModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cleans up the models that `php artisan rdw:discover` made: merges duplicates, fixes names
 * and replaces "Alle bouwjaren" by real generations for the most popular models.
 *
 * Run it after `rdw:discover` and before `rdw:calculate`. It is safe to run twice, and
 * it never touches a model that already has generations made by hand.
 *
 * The generation years are a first guess (by build year) and must be checked by hand.
 */
class ModelFixesSeeder extends Seeder
{
    public function run(): void
    {
        $this->merge();
        $this->rename();
        $this->delete();
        $this->generations();
    }

    /** Models that are really the same car under another RDW name: [brand => [source slug => target slug]] */
    private function merges(): array
    {
        return [
            'KIA' => ['cee' => 'ceed'],
            'OPEL' => ['astra-g-caravan' => 'astra', 'astra-g-cc' => 'astra', 'karl-viva' => 'karl'],
            'FORD' => ['transit-tourneo' => 'transit'],
            'VOLKSWAGEN' => ['golf-cabriolet' => 'golf', '111011' => '1200', '113021' => '1200'],
        ];
    }

    /** [brand => [slug => new name]] */
    private function renames(): array
    {
        return [
            'AUDI' => ['tt' => 'TT', 'rs' => 'RS'],
            'FORD' => ['ecosport' => 'EcoSport'],
            'TOYOTA' => ['c-hr' => 'C-HR', 'iq' => 'iQ', 'urban' => 'Urban Cruiser'],
            'PEUGEOT' => ['rcz' => 'RCZ'],
            'OPEL' => ['gt' => 'GT'],
            'VOLKSWAGEN' => ['id-buzz' => 'ID. Buzz', 'lt' => 'LT', '1200' => 'Kever'],
        ];
    }

    /** Not a car model ("VW" is a leftover of the brand name). */
    private function deletes(): array
    {
        return ['VOLKSWAGEN' => ['vw']];
    }

    /**
     * [brand => [model slug => [[code, from, to], ...]]]
     * Years must not overlap within one model.
     */
    private function generationData(): array
    {
        return [
            'AUDI' => [
                'a1' => [['8X', 2010, 2018], ['GB', 2019, null]],
                'a3' => [['8L', 1996, 2003], ['8P', 2004, 2012], ['8V', 2013, 2020], ['8Y', 2021, null]],
                'a6' => [['C4', 1995, 1997], ['C5', 1998, 2004], ['C6', 2005, 2011], ['C7', 2012, 2018], ['C8', 2019, null]],
                'q3' => [['8U', 2011, 2018], ['F3', 2019, null]],
                'q5' => [['8R', 2008, 2017], ['FY', 2018, null]],
            ],
            'FORD' => [
                'focus' => [['Mk1', 1998, 2004], ['Mk2', 2005, 2010], ['Mk3', 2011, 2017], ['Mk4', 2018, null]],
                'kuga' => [['I', 2008, 2012], ['II', 2013, 2019], ['III', 2020, null]],
            ],
            'KIA' => [
                'ceed' => [['ED', 2007, 2012], ['JD', 2013, 2018], ['CD', 2019, null]],
                'niro' => [['I', 2016, 2022], ['II', 2023, null]],
                'sportage' => [['JE', 2004, 2010], ['SL', 2011, 2015], ['QL', 2016, 2021], ['NQ5', 2022, null]],
            ],
            'OPEL' => [
                'insignia' => [['A', 2008, 2016], ['B', 2017, null]],
                'mokka' => [['A', 2012, 2019], ['B', 2020, null]],
                'zafira' => [['A', 1999, 2005], ['B', 2006, 2011], ['C', 2012, null]],
            ],
            'PEUGEOT' => [
                '2008' => [['I', 2013, 2019], ['II', 2020, null]],
                '3008' => [['I', 2009, 2016], ['II', 2017, null]],
                '308' => [['T7', 2007, 2013], ['T9', 2014, 2021], ['P5', 2022, null]],
                '5008' => [['I', 2010, 2016], ['II', 2017, null]],
                '508' => [['I', 2011, 2018], ['II', 2019, null]],
            ],
            'RENAULT' => [
                'captur' => [['I', 2013, 2019], ['II', 2020, null]],
                'megane' => [['I', 1996, 2002], ['II', 2003, 2008], ['III', 2009, 2015], ['IV', 2016, null]],
                'twingo' => [['I', 1993, 2006], ['II', 2007, 2013], ['III', 2014, null]],
            ],
            'TOYOTA' => [
                'auris' => [['I', 2007, 2012], ['II', 2013, null]],
                'aygo' => [['I', 2005, 2013], ['II', 2014, 2021], ['III', 2022, null]],
                'prius' => [['XW10', 2001, 2003], ['XW20', 2004, 2009], ['XW30', 2010, 2015], ['XW50', 2016, 2022], ['XW60', 2023, null]],
            ],
            'VOLKSWAGEN' => [
                'tiguan' => [['I', 2007, 2015], ['II', 2016, 2023], ['III', 2024, null]],
            ],
        ];
    }

    private function merge(): void
    {
        foreach ($this->merges() as $rdwBrand => $pairs) {
            foreach ($pairs as $from => $to) {
                $source = $this->model($rdwBrand, $from);
                $target = $this->model($rdwBrand, $to);

                if (! $source || ! $target) {
                    continue;
                }

                // the aliases move to the target, so its statistics include these cars
                $source->aliases()->update(['car_model_id' => $target->id]);

                // a merged model with its own year range widens the target's "Alle bouwjaren"
                $this->widenAllYears($target, $source);

                $source->delete();
            }
        }
    }

    private function rename(): void
    {
        foreach ($this->renames() as $rdwBrand => $names) {
            foreach ($names as $slug => $name) {
                $model = $this->model($rdwBrand, $slug);
                $model?->update(['name' => $name, 'slug' => Str::slug($name)]);
            }
        }
    }

    private function delete(): void
    {
        foreach ($this->deletes() as $rdwBrand => $slugs) {
            foreach ($slugs as $slug) {
                $this->model($rdwBrand, $slug)?->delete();
            }
        }
    }

    private function generations(): void
    {
        foreach ($this->generationData() as $rdwBrand => $models) {
            foreach ($models as $slug => $generations) {
                $model = $this->model($rdwBrand, $slug);

                if (! $model || ! $this->hasOnlyAllYears($model)) {
                    continue;
                }

                // do not lose cars built before the first generation we know
                $first = $model->generations()->min('year_from');
                $model->generations()->delete();

                foreach ($generations as $i => [$code, $from, $to]) {
                    $model->generations()->create([
                        'code' => $code,
                        'slug' => Str::slug($code),
                        'year_from' => $i === 0 ? min($from, $first ?? $from) : $from,
                        'year_to' => $to,
                    ]);
                }
            }
        }
    }

    private function hasOnlyAllYears(CarModel $model): bool
    {
        $slugs = $model->generations()->pluck('slug');

        return $slugs->isNotEmpty() && $slugs->every(fn ($slug) => $slug === 'alle-bouwjaren');
    }

    private function widenAllYears(CarModel $target, CarModel $source): void
    {
        $generation = $target->generations()->where('slug', 'alle-bouwjaren')->first();
        $other = $source->generations()->where('slug', 'alle-bouwjaren')->first();

        if (! $generation || ! $other) {
            return;
        }

        $generation->update([
            'year_from' => min($generation->year_from, $other->year_from),
            'year_to' => ($generation->year_to === null || $other->year_to === null) ? null : max($generation->year_to, $other->year_to),
        ]);
    }

    private function model(string $rdwBrand, string $slug): ?CarModel
    {
        $brand = Brand::where('rdw_name', $rdwBrand)->first();

        return $brand?->carModels()->where('slug', $slug)->first();
    }
}
