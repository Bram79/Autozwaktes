<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Rdw\StatisticsCalculator;
use App\Site\Sitemap;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Recalculates all statistics from the staging tables and clears the cache. */
class CalculateStatisticsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function handle(StatisticsCalculator $calculator): void
    {
        $calculator->run();

        // the cache was cleared: build the sitemap (and with it the rankings) now, so that no bot has to wait for it
        app(Sitemap::class)->warm();
    }

    /** Without this a failed calculation is only a line in failed_jobs, and nobody sees that the numbers on the site are old. */
    public function failed(Throwable $e): void
    {
        ImportRun::create([
            'dataset' => 'statistics',
            'status' => 'failed',
            'error' => mb_substr(class_basename($e).': '.$e->getMessage(), 0, 500),
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }
}
