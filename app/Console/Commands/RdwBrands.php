<?php

namespace App\Console\Commands;

use App\Rdw\BrandAdder;
use Illuminate\Console\Command;

class RdwBrands extends Command
{
    protected $signature = 'rdw:brands {--top=20 : How many of the biggest brands to look at} {--add : Add the missing brands (without this the command only shows them)}';

    protected $description = 'Show the biggest passenger car brands in the RDW data and add the ones that are missing';

    public function handle(BrandAdder $adder): int
    {
        $brands = $adder->top((int) $this->option('top'));

        $this->table(['#', 'RDW-naam', 'Naam op de site', 'Auto\'s in het register', 'Status'], array_map(fn ($b, $i) => [
            $i + 1, $b['rdw_name'], $b['name'], number_format($b['cars'], 0, ',', '.'), $b['exists'] ? 'bestaat al' : 'nieuw',
        ], $brands, array_keys($brands)));

        $new = count(array_filter($brands, fn ($b) => ! $b['exists']));

        if (! $this->option('add')) {
            $this->info("{$new} new brands. Nothing added: run again with --add.");

            return self::SUCCESS;
        }

        $this->info($adder->add($brands).' brands added.');
        $this->line('Next: `php artisan rdw:update` (imports their cars and defects, takes hours), then `rdw:discover`, `rdw:types`, `generations:propose`.');

        return self::SUCCESS;
    }
}
