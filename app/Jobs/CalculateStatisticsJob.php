<?php

namespace App\Jobs;

use App\Rdw\StatisticsCalculator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Recalculates all statistics from the staging tables and clears the cache. */
class CalculateStatisticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(StatisticsCalculator $calculator): void
    {
        $calculator->run();
    }
}
