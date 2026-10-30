<?php

namespace App\Console\Commands;

use App\Jobs\CalculateStatisticsJob;
use Illuminate\Console\Command;

class RdwCalculate extends Command
{
    protected $signature = 'rdw:calculate {--now : Run immediately instead of queueing}';

    protected $description = 'Recalculate the statistics from the imported data and clear the cache';

    public function handle(): int
    {
        $this->option('now') ? CalculateStatisticsJob::dispatchSync() : CalculateStatisticsJob::dispatch();

        $this->info($this->option('now') ? 'Statistics recalculated.' : 'Calculation queued.');

        return self::SUCCESS;
    }
}
