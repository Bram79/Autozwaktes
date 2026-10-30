<?php

namespace App\Jobs;

use App\Models\Brand;
use App\Models\ImportRun;
use App\Rdw\RdwClient;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Plans the import of one dataset: checks the columns, counts the rows and
 * queues one ImportPageJob per chunk. When all chunks are done, the next step
 * in $next runs (another dataset, or 'calculate' for the statistics).
 */
class PlanDatasetImportJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    /** @param string[] $next datasets still to import after this one; 'calculate' means: recalculate statistics */
    public function __construct(public string $dataset, public array $next = []) {}

    public function handle(RdwClient $client): void
    {
        $run = ImportRun::create(['dataset' => $this->dataset, 'started_at' => now()]);

        try {
            $client->assertColumnsExist($this->dataset);

            $extraWhere = $this->extraWhere();
            $total = $client->count($this->dataset, $extraWhere);
            $limit = config('rdw.chunk_size');

            $jobs = [];
            for ($offset = 0; $offset < $total; $offset += $limit) {
                $jobs[] = new ImportPageJob($this->dataset, $offset, $limit, $run->id, $extraWhere);
            }
        } catch (Throwable $e) {
            $this->fail($run, $e->getMessage());

            throw $e;
        }

        Log::info("RDW import '{$this->dataset}': {$total} rows in ".count($jobs).' chunks.');

        if ($jobs === []) {
            $this->finish($run->id, $this->dataset, $this->next);

            return;
        }

        $runId = $run->id;
        $dataset = $this->dataset;
        $next = $this->next;

        Bus::batch($jobs)
            ->name("rdw-import-{$dataset}")
            ->then(fn (Batch $batch) => self::finish($runId, $dataset, $next))
            ->catch(fn (Batch $batch, Throwable $e) => self::markFailed($runId, $e->getMessage()))
            ->dispatch();
    }

    /** Vehicles: only import the brands we have in the database. */
    private function extraWhere(): array
    {
        if ($this->dataset !== 'vehicles') {
            return [];
        }

        $names = Brand::pluck('rdw_name')
            ->map(fn ($name) => "'".str_replace("'", "''", $name)."'")
            ->implode(', ');

        return $names === '' ? ["merk = ''"] : ["merk in ({$names})"];
    }

    public static function finish(int $runId, string $dataset, array $next): void
    {
        ImportRun::whereKey($runId)->update(['status' => 'finished', 'finished_at' => now()]);
        Log::info("RDW import '{$dataset}' finished.");

        if ($next === []) {
            return;
        }

        $step = array_shift($next);

        if ($step === 'calculate') {
            CalculateStatisticsJob::dispatch();
        } else {
            self::dispatch($step, $next);
        }
    }

    public static function markFailed(int $runId, string $error): void
    {
        ImportRun::whereKey($runId)->update(['status' => 'failed', 'error' => $error, 'finished_at' => now()]);
        Log::error("RDW import failed: {$error}");
    }

    private function fail(ImportRun $run, string $error): void
    {
        self::markFailed($run->id, $error);
    }
}
