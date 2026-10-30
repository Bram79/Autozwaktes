<?php

namespace App\Console\Commands;

use App\Rdw\TypeImporter;
use Illuminate\Console\Command;

class RdwTypes extends Command
{
    protected $signature = 'rdw:types';

    protected $description = 'Fetch the RDW type codes per model and year (input for generations:propose)';

    public function handle(TypeImporter $importer): int
    {
        $this->info($importer->import().' rows saved.');

        return self::SUCCESS;
    }
}
