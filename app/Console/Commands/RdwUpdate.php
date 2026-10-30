<?php

namespace App\Console\Commands;

use App\Jobs\PlanDatasetImportJob;
use Illuminate\Console\Command;

class RdwUpdate extends Command
{
    protected $signature = 'rdw:update';

    protected $description = 'Run the full RDW import (defect codes, vehicles, fuel, defects) and recalculate the statistics';

    public function handle(): int
    {
        PlanDatasetImportJob::dispatch('defect_codes', ['vehicles', 'fuel', 'defects', 'calculate']);

        $this->info('Import queued. Make sure a queue worker is running: php artisan queue:work');

        return self::SUCCESS;
    }
}
