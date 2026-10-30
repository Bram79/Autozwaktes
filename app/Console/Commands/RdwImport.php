<?php

namespace App\Console\Commands;

use App\Jobs\PlanDatasetImportJob;
use Illuminate\Console\Command;

class RdwImport extends Command
{
    protected $signature = 'rdw:import {dataset : defect_codes, vehicles, fuel or defects}';

    protected $description = 'Import a single RDW dataset (without recalculating the statistics)';

    public function handle(): int
    {
        $dataset = $this->argument('dataset');

        if (! array_key_exists($dataset, config('rdw.datasets'))) {
            $this->error('Unknown dataset. Choose: '.implode(', ', array_keys(config('rdw.datasets'))));

            return self::FAILURE;
        }

        PlanDatasetImportJob::dispatch($dataset);

        $this->info("Import of '{$dataset}' queued. Make sure a queue worker is running.");

        return self::SUCCESS;
    }
}
