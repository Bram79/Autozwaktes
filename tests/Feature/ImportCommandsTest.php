<?php

use App\Jobs\PlanDatasetImportJob;
use App\Models\CarModel;
use App\Models\GenerationProposal;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed(StarterModelsSeeder::class);
});

// --- rdw:import and rdw:update plan the work, they do not do it themselves --------------------------------------------------

it('queues the import of one RDW dataset', function () {
    Queue::fake();

    $this->artisan('rdw:import defects')->assertSuccessful()->expectsOutputToContain("Import of 'defects' queued");

    Queue::assertPushed(PlanDatasetImportJob::class, fn ($job) => $job->dataset === 'defects' && $job->next === []);
});

it('refuses a dataset that does not exist, and says which ones do', function () {
    Queue::fake();

    $this->artisan('rdw:import voertuigen')->assertFailed()->expectsOutputToContain('Unknown dataset. Choose: defect_codes');

    Queue::assertNothingPushed();
});

it('starts the full update with the defect codes, and lines up the other datasets and the calculation behind it', function () {
    Queue::fake();

    $this->artisan('rdw:update')->assertSuccessful()->expectsOutputToContain('Import queued');

    Queue::assertPushed(PlanDatasetImportJob::class, fn ($job) => $job->dataset === 'defect_codes' && $job->next === ['vehicles', 'fuel', 'defects', 'calculate']);
});

// --- rdw:types ----------------------------------------------------------------------------------------------------------------

it('fetches the type codes and prices and tells how many rows were saved', function () {
    Http::fake(['opendata.rdw.nl/*' => function ($request) {
        $isPriceQuery = str_contains($request['$select'] ?? '', 'avg(catalogusprijs)');

        return Http::response($isPriceQuery ? [] : [['handelsbenaming' => 'POLO', 'type' => '6R', 'jaar' => '2012', 'n' => '300']]);
    }]);

    $this->artisan('rdw:types')->assertSuccessful()->expectsOutputToContain('rows saved');

    expect(DB::table('vehicle_types')->where('build_year', 2012)->where('type', '6R')->value('vehicles'))->toBe(300);
});

// --- rdw:unmatched ------------------------------------------------------------------------------------------------------------

it('shows the RDW model names that match none of our models, the most common first', function () {
    $rows = [['AAA111', 'VOLKSWAGEN', 'POLO'], ['BBB222', 'LANCIA', 'DELTA'], ['CCC333', 'LANCIA', 'DELTA'], ['DDD444', 'LANCIA', 'YPSILON']];
    foreach ($rows as [$plate, $brand, $model]) {
        DB::table('staging_vehicles')->insert(['license_plate' => $plate, 'brand_raw' => $brand, 'model_raw' => $model, 'build_year' => 2010]);
    }

    $this->artisan('rdw:unmatched --limit=5')->assertSuccessful()
        ->expectsOutputToContain('DELTA')->expectsOutputToContain('YPSILON');   // the Polo is one of ours: not in the list
});

// --- generations:apply --------------------------------------------------------------------------------------------------------

it('puts approved proposals on the site and says what was skipped', function () {
    $polo = CarModel::where('slug', 'polo')->first();
    $polo->generations()->delete();
    $polo->generations()->create(['code' => 'Alle bouwjaren', 'slug' => 'alle-bouwjaren', 'year_from' => 1995, 'year_to' => null]);
    GenerationProposal::create(['car_model_id' => $polo->id, 'code' => '9N', 'year_from' => 2001, 'year_to' => 2008, 'confidence' => 80, 'source' => 'rdw', 'status' => 'approved']);
    GenerationProposal::create(['car_model_id' => $polo->id, 'code' => '6R', 'year_from' => 2009, 'year_to' => null, 'confidence' => 80, 'source' => 'rdw']);   // still open

    $this->artisan('generations:apply')->assertSuccessful()->expectsOutputToContain('skipped')->expectsOutputToContain('rdw:calculate');
    expect($polo->generations()->pluck('code')->all())->toBe(['Alle bouwjaren']);   // not while a proposal is open

    GenerationProposal::where('code', '6R')->update(['status' => 'approved']);

    $this->artisan('generations:apply')->assertSuccessful()->expectsOutputToContain('applied');
    expect($polo->generations()->orderBy('year_from')->pluck('code')->all())->toBe(['9N', '6R']);
});
