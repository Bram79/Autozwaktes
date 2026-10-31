<?php

namespace App\Console\Commands;

use App\Ai\AdviceDrafter;
use App\Models\AdviceDraft;
use App\Models\Generation;
use App\Site\Seo;
use Illuminate\Console\Command;

class AdviceDraftCommand extends Command
{
    protected $signature = 'advice:draft {--model= : Only generations of this model (slug)} {--limit=10 : Maximum number of drafts}';

    protected $description = 'Let Claude write draft buying advices from our statistics (needs AI_ADVICE_ENABLED and ANTHROPIC_API_KEY)';

    public function handle(AdviceDrafter $drafter): int
    {
        if (! $drafter->enabled()) {
            $this->error('The AI link is off. Set AI_ADVICE_ENABLED=true and ANTHROPIC_API_KEY in .env.');

            return self::FAILURE;
        }

        // generations without an advice of their own, and without a draft that nobody has looked at
        $generations = Generation::with('carModel.brand')
            ->where(fn ($q) => $q->whereNull('buying_advice')->orWhere('buying_advice', ''))
            ->whereDoesntHave('adviceDrafts', fn ($q) => $q->whereIn('status', [AdviceDraft::CONCEPT, AdviceDraft::APPROVED]))
            ->whereIn('id', fn ($q) => $q->select('generation_id')->from('defect_stats'))
            ->when($this->option('model'), fn ($q, $slug) => $q->whereHas('carModel', fn ($m) => $m->where('slug', $slug)))
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($generations as $generation) {
            $result = $drafter->draft($generation);
            $name = Seo::generationName($generation);

            if ($result instanceof AdviceDraft) {
                $this->line($result->status === AdviceDraft::CONCEPT ? "<info>draft</info>    {$name}" : "<comment>rejected</comment> {$name}: {$result->note}");
            } else {
                $this->line("<error>failed</error>   {$name}: {$result}");
            }
        }

        $this->info('Review the drafts in the admin under "Koopadvies-concepten".');

        return self::SUCCESS;
    }
}
