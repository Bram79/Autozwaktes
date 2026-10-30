<?php

namespace App\Jobs;

use App\Models\ImportRun;
use App\Rdw\PageImporter;
use App\Rdw\RdwClient;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Downloads one page (chunk) of a dataset and saves it. */
class ImportPageJob implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 5;

    public int $timeout = 600;

    /** @param string[] $extraWhere */
    public function __construct(
        public string $dataset,
        public int $offset,
        public int $limit,
        public int $importRunId,
        public array $extraWhere = [],
    ) {}

    public function backoff(): array
    {
        return [30, 120, 300, 600];
    }

    public function handle(RdwClient $client, PageImporter $importer): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $rows = $client->fetchPage($this->dataset, $this->offset, $this->limit, $this->extraWhere);
        $importer->import($this->dataset, $rows);

        ImportRun::whereKey($this->importRunId)->increment('rows_imported', count($rows));
    }
}
