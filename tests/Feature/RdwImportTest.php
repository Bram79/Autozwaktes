<?php

use App\Jobs\PlanDatasetImportJob;
use App\Models\ImportRun;
use App\Rdw\RdwClient;
use App\Rdw\RdwSchemaChangedException;
use App\Rdw\RdwUnavailableException;
use Database\Seeders\StarterModelsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function fakeViewColumns(array $columns): array
{
    return ['columns' => array_map(fn ($c) => ['fieldName' => $c], $columns)];
}

beforeEach(function () {
    config(['rdw.chunk_size' => 2]);
    $this->seed(StarterModelsSeeder::class);
});

it('imports vehicles page by page and is idempotent', function () {
    $columns = config('rdw.datasets.vehicles.columns');

    Http::fake([
        '*/api/views/*' => Http::response(fakeViewColumns($columns)),
        '*select=count*' => Http::response([['count' => '3']]),
        '*offset=0*' => Http::response([
            ['kenteken' => 'AA1', 'merk' => 'AUDI', 'handelsbenaming' => 'A4 AVANT', 'datum_eerste_toelating_dt' => '2010-05-01T00:00:00.000'],
            ['kenteken' => 'AA2', 'merk' => 'AUDI', 'handelsbenaming' => 'A4', 'datum_eerste_toelating_dt' => '2011-01-01T00:00:00.000'],
        ]),
        '*offset=2*' => Http::response([
            ['kenteken' => 'AA3', 'merk' => 'AUDI', 'handelsbenaming' => 'A4', 'datum_eerste_toelating_dt' => '2012-01-01T00:00:00.000'],
        ]),
    ]);

    PlanDatasetImportJob::dispatchSync('vehicles');
    PlanDatasetImportJob::dispatchSync('vehicles');   // second run: no duplicates

    expect(DB::table('staging_vehicles')->count())->toBe(3);
    expect(DB::table('staging_vehicles')->where('license_plate', 'AA1')->value('build_year'))->toBe(2010);
    expect(ImportRun::where('status', 'finished')->count())->toBe(2);
});

it('stores only defects of known vehicles', function () {
    DB::table('staging_vehicles')->insert(['license_plate' => 'AA1', 'brand_raw' => 'AUDI', 'model_raw' => 'A4']);
    $columns = config('rdw.datasets.defects.columns');

    Http::fake([
        '*/api/views/*' => Http::response(fakeViewColumns($columns)),
        '*select=count*' => Http::response([['count' => '2']]),
        '*offset=0*' => Http::response([
            ['kenteken' => 'AA1', 'gebrek_identificatie' => '576', 'meld_datum_door_keuringsinstantie_dt' => '2024-03-19T16:06:00.000', 'aantal_gebreken_geconstateerd' => '1'],
            ['kenteken' => 'ZZ9', 'gebrek_identificatie' => '576', 'meld_datum_door_keuringsinstantie_dt' => '2024-03-19T16:06:00.000', 'aantal_gebreken_geconstateerd' => '1'],
        ]),
    ]);

    PlanDatasetImportJob::dispatchSync('defects');
    PlanDatasetImportJob::dispatchSync('defects');

    expect(DB::table('staging_defects')->count())->toBe(1);
});

it('stops with a clear error when RDW columns have changed', function () {
    Http::fake(['*/api/views/*' => Http::response(fakeViewColumns(['kenteken']))]);

    expect(fn () => PlanDatasetImportJob::dispatchSync('vehicles'))->toThrow(RdwSchemaChangedException::class, 'column(s) missing');

    expect(ImportRun::first())->status->toBe('failed')->error->toContain('merk');
});

it('stops with a clear error when the RDW API is not reachable', function () {
    Http::fake(['*' => Http::response('down', 503)]);

    expect(fn () => app(RdwClient::class)->count('vehicles'))->toThrow(RdwUnavailableException::class);
});
