<?php

namespace App\Console\Commands;

use App\Rdw\ModelDiscoverer;
use Illuminate\Console\Command;

class RdwDiscover extends Command
{
    protected $signature = 'rdw:discover {--min=1000 : Minimum number of vehicles for a model} {--dry-run : Only show what would be created}';

    protected $description = 'Create car models (with aliases and one generation) from the imported RDW vehicles';

    public function handle(ModelDiscoverer $discoverer): int
    {
        $found = $discoverer->discover((int) $this->option('min'));

        $this->table(
            ['Brand', 'Model', 'Vehicles', 'Years'],
            array_map(fn ($m) => [$m['brand']->name, $m['name'], number_format($m['vehicles'], 0, ',', '.'), $m['year_from'].'–'.($m['year_to'] ?? '')], $found),
        );

        if ($this->option('dry-run')) {
            $this->info(count($found).' new models found. Nothing was created (dry run).');

            return self::SUCCESS;
        }

        $created = $discoverer->create($found);
        $this->info("{$created} models created. Run `php artisan rdw:calculate` to calculate their statistics.");

        return self::SUCCESS;
    }
}
