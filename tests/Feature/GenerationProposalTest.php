<?php

use App\Filament\Resources\GenerationProposals\Pages\ManageGenerationProposals;
use App\Generations\GenerationProposer;
use App\Models\CarModel;
use App\Models\Generation;
use App\Models\GenerationProposal;
use App\Models\User;
use App\Rdw\TypeImporter;
use App\Wikidata\WikidataClient;
use Database\Seeders\StarterModelsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);
    $this->polo = CarModel::where('slug', 'polo')->first();
    $this->polo->generations()->delete();
    $this->polo->generations()->create(['code' => 'Alle bouwjaren', 'slug' => 'alle-bouwjaren', 'year_from' => 1995, 'year_to' => null]);
});

/** Cars per year of one RDW type: types($model, '6R', 2009, 2017, 1000) */
function types(CarModel $model, string $type, int $from, int $to, int $perYear): void
{
    foreach (range($from, $to) as $year) {
        DB::table('vehicle_types')->insert(['car_model_id' => $model->id, 'type' => $type, 'build_year' => $year, 'vehicles' => $perYear]);
    }
}

/** The three RDW types of the Polo, with a year of overlap each. */
function poloTypes(CarModel $model): void
{
    types($model, '9N', 2001, 2009, 3000);
    types($model, '6R', 2009, 2018, 14000);
    types($model, 'AW', 2017, 2025, 11000);
    DB::table('vehicle_types')->where('type', '9N')->where('build_year', 2009)->update(['vehicles' => 500]);
    DB::table('vehicle_types')->where('type', '6R')->where('build_year', 2017)->update(['vehicles' => 4000]);
}

it('proposes generations from the RDW type codes alone', function () {
    poloTypes($this->polo);

    $proposals = app(GenerationProposer::class)->propose($this->polo);

    expect(array_column($proposals, 'code'))->toBe(['9N', '6R', 'AW']);
    expect(array_column($proposals, 'year_from'))->toBe([2001, 2009, 2017]);
    expect(array_column($proposals, 'year_to'))->toBe([2008, 2016, null]);   // the last one is still being built
    expect(array_unique(array_column($proposals, 'source')))->toBe(['rdw']);
});

it('gives a higher confidence and the familiar name when Wikidata agrees', function () {
    poloTypes($this->polo);
    $wikidata = [
        ['short' => 'Mk3', 'start' => 1994, 'end' => 2000],
        ['short' => 'Mk4', 'start' => 2001, 'end' => 2008],
        ['short' => 'Mk5', 'start' => 2009, 'end' => null],
    ];
    DB::table('vehicle_types')->insert(['car_model_id' => $this->polo->id, 'type' => '', 'build_year' => 1997, 'vehicles' => 900]);

    $proposals = collect(app(GenerationProposer::class)->propose($this->polo, $wikidata))->keyBy('code');

    expect($proposals->keys()->all())->toBe(['Mk3', 'Mk4 (9N)', 'Mk5 (6R)', 'AW']);
    expect($proposals['Mk4 (9N)']['confidence'])->toBeGreaterThanOrEqual(90);
    expect($proposals['Mk4 (9N)']['source'])->toBe('rdw+wikidata');
    expect($proposals['Mk3']['source'])->toBe('wikidata');     // old cars have no RDW type code
    expect($proposals['AW']['confidence'])->toBeLessThan($proposals['Mk4 (9N)']['confidence']);
});

it('splits one RDW type where Wikidata knows two generations', function () {
    types($this->polo, '1K', 2003, 2012, 8000);
    types($this->polo, 'AU', 2012, 2020, 9000);
    DB::table('vehicle_types')->where('type', '1K')->where('build_year', 2012)->update(['vehicles' => 1000]);

    $wikidata = [
        ['short' => 'Mk5', 'start' => 2003, 'end' => 2008],
        ['short' => 'Mk6', 'start' => 2008, 'end' => 2012],
        ['short' => 'Mk7', 'start' => 2012, 'end' => null],
    ];

    $proposals = app(GenerationProposer::class)->propose($this->polo, $wikidata);

    expect(array_column($proposals, 'code'))->toBe(['Mk5', 'Mk6', 'Mk7 (AU)']);
    expect(array_column($proposals, 'year_from'))->toBe([2003, 2008, 2012]);
});

it('ignores type codes with only a few cars and does not propose a single generation', function () {
    types($this->polo, '6R', 2009, 2018, 14000);
    types($this->polo, 'XYZ', 2012, 2012, 10);   // noise

    expect(app(GenerationProposer::class)->propose($this->polo))->toBe([]);
});

it('merges type codes that start the same way', function () {
    types($this->polo, 'AU', 2012, 2018, 9000);
    types($this->polo, 'AUV', 2013, 2018, 3000);
    types($this->polo, 'CD', 2019, 2025, 8000);

    $proposals = app(GenerationProposer::class)->propose($this->polo);

    expect(array_column($proposals, 'code'))->toBe(['AU', 'CD']);
});

it('only accepts real generation names from Wikidata', function () {
    foreach (['Mk5', 'Mk IV', 'IV', 'D', 'B8', '3rd generation'] as $name) {
        expect(WikidataClient::looksLikeGeneration($name))->toBeTrue($name);
    }
    foreach (['Classic', 'Playa', 'Variant', 'GTI R5'] as $name) {
        expect(WikidataClient::looksLikeGeneration($name))->toBeFalse($name);
    }
});

it('reads the generations from a Wikidata answer, and finds nothing when Wikidata is down', function () {
    Http::fakeSequence('query.wikidata.org/*')
        ->push(['results' => ['bindings' => [
            ['label' => ['value' => 'Volkswagen Polo Mk5'], 'start' => ['value' => '2009-01-01T00:00:00Z']],
            ['label' => ['value' => 'Volkswagen Polo Mk4'], 'start' => ['value' => '2001-01-01T00:00:00Z'], 'end' => ['value' => '2009-01-01T00:00:00Z']],
            ['label' => ['value' => 'Volkswagen Polo Classic'], 'start' => ['value' => '1999-01-01T00:00:00Z']],
            ['label' => ['value' => 'Volkswagen Polo Mk3']],   // no start year
        ]]])
        ->push('down', 503)
        ->push('down', 503);

    $wikidata = app(WikidataClient::class);

    expect($wikidata->generations('Q247819', 'Volkswagen Polo'))->toBe([
        ['short' => 'Mk5', 'start' => 2009, 'end' => null],
        ['short' => 'Mk4', 'start' => 2001, 'end' => 2009],
    ]);
    expect($wikidata->generations('Q247819', 'Volkswagen Polo'))->toBe([]);
});

it('finds the item of a series only when the label matches exactly', function () {
    Http::fake(['www.wikidata.org/*' => Http::response(['search' => [
        ['id' => 'Q1', 'label' => 'Volkswagen Polo R WRC', 'description' => 'rally car'],
        ['id' => 'Q247819', 'label' => 'Volkswagen Polo', 'description' => 'Series of automobiles manufactured by Volkswagen'],
    ]])]);

    expect(app(WikidataClient::class)->seriesId('Volkswagen', 'Polo'))->toBe('Q247819');
});

it('saves proposals with the command and approves the sure ones', function () {
    poloTypes($this->polo);

    $this->artisan('generations:propose --model=polo --no-wikidata --auto-approve=60')->assertSuccessful();

    $saved = GenerationProposal::where('car_model_id', $this->polo->id)->orderBy('year_from')->get();
    expect($saved)->toHaveCount(3);
    expect($saved->pluck('status')->unique()->all())->toBe(['approved']);   // 65% >= 60

    // running it again replaces the open ones but never touches a model that already has hand-made generations
    $this->polo->generations()->delete();
    $this->polo->generations()->create(['code' => 'Mijn generatie', 'slug' => 'mijn-generatie', 'year_from' => 2000]);
    GenerationProposal::query()->delete();
    $this->artisan('generations:propose --model=polo --no-wikidata')->assertSuccessful();
    expect(GenerationProposal::count())->toBe(0);
});

it('applies approved proposals, but not while some are open or when a buying advice would be lost', function () {
    foreach ([['9N', 2001, 2008], ['6R', 2009, 2016]] as [$code, $from, $to]) {
        GenerationProposal::create(['car_model_id' => $this->polo->id, 'code' => $code, 'year_from' => $from, 'year_to' => $to, 'confidence' => 80, 'source' => 'rdw', 'status' => 'approved']);
    }
    $open = GenerationProposal::create(['car_model_id' => $this->polo->id, 'code' => 'AW', 'year_from' => 2017, 'confidence' => 60, 'source' => 'rdw']);

    expect(GenerationProposal::applyFor($this->polo))->toContain('open');
    expect($this->polo->generations()->count())->toBe(1);   // nothing changed

    $open->update(['status' => 'approved']);
    $this->polo->generations()->first()->update(['buying_advice' => 'Mijn tekst']);
    expect(GenerationProposal::applyFor($this->polo))->toContain('koopadvies');

    $this->polo->generations()->update(['buying_advice' => null]);
    expect(GenerationProposal::applyFor($this->polo))->toBeNull();

    expect($this->polo->generations()->orderBy('year_from')->pluck('code')->all())->toBe(['9N', '6R', 'AW']);
    expect(GenerationProposal::count())->toBe(0);   // done, nothing left to review
});

it('lets the admin approve, change and apply proposals', function () {
    config(['site.admin_emails' => ['beheer@example.com']]);
    $this->actingAs(User::factory()->create(['email' => 'beheer@example.com']));

    $a = GenerationProposal::create(['car_model_id' => $this->polo->id, 'code' => '9N', 'year_from' => 2001, 'year_to' => 2008, 'confidence' => 90, 'source' => 'rdw+wikidata']);
    $b = GenerationProposal::create(['car_model_id' => $this->polo->id, 'code' => 'AW', 'year_from' => 2009, 'confidence' => 40, 'source' => 'rdw']);

    Livewire::test(ManageGenerationProposals::class)
        ->callAction(TestAction::make('approve')->table($a))
        ->callAction(TestAction::make('edit')->table($b), ['code' => 'Mk6', 'year_from' => 2010, 'year_to' => null])
        ->assertHasNoActionErrors()
        ->callAction('apply');

    expect($this->polo->generations()->orderBy('year_from')->get()->map(fn (Generation $g) => [$g->code, $g->year_from, $g->year_to])->all())
        ->toBe([['9N', 2001, 2008], ['Mk6', 2010, null]]);
});

it('saves the RDW type codes and the catalogue prices per model and year', function () {
    Http::fake(['opendata.rdw.nl/*' => function ($request) {
        $isPriceQuery = str_contains($request['$select'] ?? '', 'avg(catalogusprijs)');

        return Http::response($isPriceQuery ? [
            ['handelsbenaming' => 'POLO', 'jaar' => '2012', 'n' => '100', 'prijs' => '15000.5'],
            ['handelsbenaming' => 'POLO 44 KW', 'jaar' => '2012', 'n' => '100', 'prijs' => '17000.5'],
        ] : [
            ['handelsbenaming' => 'POLO', 'type' => '6 R', 'jaar' => '2012', 'n' => '300'],
            ['handelsbenaming' => 'POLO 44 KW', 'type' => '6R', 'jaar' => '2012', 'n' => '20'],
            ['handelsbenaming' => 'POLO', 'type' => '-', 'jaar' => '1999', 'n' => '50'],
            ['handelsbenaming' => 'ID. POLO', 'type' => 'X', 'jaar' => '2026', 'n' => '5'],   // not a model of ours
        ]);
    }]);

    app(TypeImporter::class)->import();

    $rows = DB::table('vehicle_types')->where('car_model_id', $this->polo->id)->orderBy('build_year')->get();
    expect($rows->map(fn ($r) => [$r->type, $r->build_year, $r->vehicles])->all())->toBe([['', 1999, 50], ['6R', 2012, 320]]);

    // only the Volkswagen brand has a Polo: the average of 100 x 15000.5 and 100 x 17000.5
    $price = DB::table('model_prices')->where('car_model_id', $this->polo->id)->first();
    expect($price->average_price)->toBe(16001)->and($price->vehicles)->toBe(200);
});

it('does not flip between two types that start in the same year', function () {
    foreach (range(2007, 2016) as $year) {
        DB::table('vehicle_types')->insert([
            ['car_model_id' => $this->polo->id, 'type' => 'AAA', 'build_year' => $year, 'vehicles' => $year % 2 ? 900 : 1000],
            ['car_model_id' => $this->polo->id, 'type' => 'BBB', 'build_year' => $year, 'vehicles' => $year % 2 ? 1000 : 900],
        ]);
    }
    types($this->polo, 'CCC', 2017, 2025, 2000);

    $proposals = app(GenerationProposer::class)->propose($this->polo);

    expect(array_column($proposals, 'year_from'))->toBe([2007, 2017]);
});

it('does not propose anything when there is only one real generation next to the old years', function () {
    types($this->polo, '', 1990, 2000, 300);
    types($this->polo, '6R', 2009, 2018, 14000);

    expect(app(GenerationProposer::class)->propose($this->polo))->toBe([]);
});

it('shows cut-off or noted RDW type codes clean and trusts them less', function () {
    types($this->polo, 'XA5(EU,M)', 2019, 2024, 6000);
    types($this->polo, 'P*****', 2014, 2018, 6000);

    $proposals = collect(app(GenerationProposer::class)->propose($this->polo))->keyBy('code');

    expect($proposals->keys()->all())->toBe(['P', 'XA5']);
    expect($proposals['P']['confidence'])->toBeLessThan($proposals['XA5']['confidence']);
});
