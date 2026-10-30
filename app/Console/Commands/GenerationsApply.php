<?php

namespace App\Console\Commands;

use App\Models\CarModel;
use App\Models\GenerationProposal;
use Illuminate\Console\Command;

class GenerationsApply extends Command
{
    protected $signature = 'generations:apply';

    protected $description = 'Put the approved generation proposals on the site (models with proposals still open are skipped)';

    public function handle(): int
    {
        $modelIds = GenerationProposal::where('status', GenerationProposal::APPROVED)->distinct()->pluck('car_model_id');

        foreach (CarModel::with('brand')->whereIn('id', $modelIds)->get() as $model) {
            $reason = GenerationProposal::applyFor($model);
            $this->line(($reason ? '<comment>skipped</comment> ' : '<info>applied</info> ')."{$model->brand->name} {$model->name}".($reason ? ": {$reason}" : ''));
        }

        $this->info('Now run `php artisan rdw:calculate` to recalculate the statistics.');

        return self::SUCCESS;
    }
}
