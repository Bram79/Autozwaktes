<?php

namespace Database\Seeders;

use App\Models\Brand;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Starter data: brands, models, aliases and generations.
 *
 * The generation years are a first guess based on the build year (not the
 * model-year start) and are meant to be corrected by hand later.
 * Years must not overlap within one model.
 */
class StarterModelsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $rdwBrand => $brandData) {
            $brand = Brand::updateOrCreate(
                ['rdw_name' => $rdwBrand],
                ['name' => $brandData['name'], 'slug' => Str::slug($brandData['name'])],
            );

            foreach ($brandData['models'] as $modelData) {
                $model = $brand->carModels()->updateOrCreate(
                    ['slug' => Str::slug($modelData['name'])],
                    ['name' => $modelData['name']],
                );

                // one alias per model: "^POLO( |$)" matches "POLO" and "POLO 44 KW", not "ID. POLO"
                $model->aliases()->updateOrCreate(
                    ['pattern' => '^'.preg_quote(Str::upper($modelData['name']), '/').'( |$)'],
                    ['priority' => 100],
                );

                foreach ($modelData['generations'] as [$code, $from, $to]) {
                    $model->generations()->updateOrCreate(
                        ['slug' => Str::slug($code)],
                        ['code' => $code, 'year_from' => $from, 'year_to' => $to],
                    );
                }
            }
        }
    }

    /** @return array<string, array{name: string, models: array}> keyed by the RDW brand name */
    private function data(): array
    {
        return [
            'VOLKSWAGEN' => ['name' => 'Volkswagen', 'models' => [
                ['name' => 'Polo', 'generations' => [['6N', 1994, 2001], ['9N', 2002, 2009], ['6R', 2010, 2017], ['AW', 2018, null]]],
                ['name' => 'Golf', 'generations' => [['Mk3', 1991, 1997], ['Mk4', 1998, 2003], ['Mk5', 2004, 2008], ['Mk6', 2009, 2012], ['Mk7', 2013, 2019], ['Mk8', 2020, null]]],
            ]],
            'OPEL' => ['name' => 'Opel', 'models' => [
                ['name' => 'Corsa', 'generations' => [['B', 1993, 2000], ['C', 2001, 2006], ['D', 2007, 2014], ['E', 2015, 2019], ['F', 2020, null]]],
                ['name' => 'Astra', 'generations' => [['F', 1991, 1997], ['G', 1998, 2003], ['H', 2004, 2009], ['J', 2010, 2015], ['K', 2016, 2021], ['L', 2022, null]]],
            ]],
            'AUDI' => ['name' => 'Audi', 'models' => [
                ['name' => 'A4', 'generations' => [['B5', 1994, 2000], ['B6', 2001, 2004], ['B7', 2005, 2007], ['B8', 2008, 2015], ['B9', 2016, null]]],
            ]],
            'FORD' => ['name' => 'Ford', 'models' => [
                ['name' => 'Fiesta', 'generations' => [['Mk4', 1989, 1995], ['Mk5', 1996, 2001], ['Mk6', 2002, 2008], ['Mk7', 2009, 2016], ['Mk8', 2017, 2023]]],
            ]],
            'PEUGEOT' => ['name' => 'Peugeot', 'models' => [
                ['name' => '208', 'generations' => [['I', 2012, 2019], ['II', 2020, null]]],
            ]],
            'RENAULT' => ['name' => 'Renault', 'models' => [
                ['name' => 'Clio', 'generations' => [['I', 1990, 1997], ['II', 1998, 2005], ['III', 2006, 2012], ['IV', 2013, 2019], ['V', 2020, null]]],
            ]],
            'TOYOTA' => ['name' => 'Toyota', 'models' => [
                ['name' => 'Yaris', 'generations' => [['XP10', 1999, 2005], ['XP90', 2006, 2010], ['XP130', 2011, 2019], ['XP210', 2020, null]]],
            ]],
            'KIA' => ['name' => 'Kia', 'models' => [
                ['name' => 'Picanto', 'generations' => [['SA', 2004, 2010], ['TA', 2011, 2016], ['JA', 2017, null]]],
            ]],
        ];
    }
}
