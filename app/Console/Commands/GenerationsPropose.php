<?php

namespace App\Console\Commands;

use App\Generations\GenerationProposer;
use App\Models\CarModel;
use App\Models\GenerationProposal;
use App\Wikidata\WikidataClient;
use Illuminate\Console\Command;

class GenerationsPropose extends Command
{
    protected $signature = 'generations:propose
        {--model= : Only this model (slug or id)}
        {--no-wikidata : Use only the RDW type codes}
        {--include-existing : Also models whose generations were made by hand}
        {--auto-approve= : Approve proposals with at least this confidence (0-100)}
        {--dry-run : Show the proposals without saving them}';

    protected $description = 'Propose generations per model from the RDW type codes and Wikidata (run rdw:types first)';

    public function handle(GenerationProposer $proposer, WikidataClient $wikidata): int
    {
        $models = CarModel::with('brand', 'generations')->orderBy('brand_id')->orderBy('name');

        if ($filter = $this->option('model')) {
            $models->where(fn ($q) => $q->where('slug', $filter)->orWhere('id', is_numeric($filter) ? $filter : 0));
        }

        $auto = $this->option('auto-approve') === null ? null : (int) $this->option('auto-approve');
        $saved = 0;

        foreach ($models->get() as $model) {
            // a model with hand-made generations is left alone, unless asked for
            $onlyAllYears = $model->generations->every(fn ($g) => $g->slug === 'alle-bouwjaren');
            if (! $onlyAllYears && ! $this->option('include-existing')) {
                continue;
            }

            $generations = $this->option('no-wikidata') ? [] : $this->wikidataFor($model, $wikidata);
            $proposals = $proposer->propose($model, $generations);

            // new proposals replace the ones that nobody looked at yet (also when there are none left)
            if (! $this->option('dry-run')) {
                GenerationProposal::where('car_model_id', $model->id)->where('status', GenerationProposal::PENDING)->delete();
            }

            if ($proposals === []) {
                continue;
            }

            $this->line("<info>{$model->brand->name} {$model->name}</info>");
            $this->table(['Generatie', 'Van', 'Tot', 'Zekerheid', 'Bron', 'Auto\'s'], array_map(fn ($p) => [
                $p['code'], $p['year_from'], $p['year_to'] ?? 'heden', $p['confidence'].'%', $p['source'], number_format($p['vehicles'], 0, ',', '.'),
            ], $proposals));

            if ($this->option('dry-run')) {
                continue;
            }

            foreach ($proposals as $proposal) {
                GenerationProposal::create($proposal + [
                    'car_model_id' => $model->id,
                    'status' => $auto !== null && $proposal['confidence'] >= $auto ? GenerationProposal::APPROVED : GenerationProposal::PENDING,
                ]);
                $saved++;
            }
        }

        $this->info($this->option('dry-run') ? 'Dry run: nothing saved.' : "{$saved} proposals saved. Review them in the admin under 'Generatievoorstellen'.");

        return self::SUCCESS;
    }

    /** @return array<int, array{short: string, start: int, end: int|null}> */
    private function wikidataFor(CarModel $model, WikidataClient $wikidata): array
    {
        $id = $model->wikidata_id ?: $wikidata->seriesId($model->brand->name, $model->name);

        if (! $id) {
            return [];
        }

        if ($model->wikidata_id !== $id) {
            $model->update(['wikidata_id' => $id]);
        }

        usleep(300_000);   // be friendly to Wikidata

        return $wikidata->generations($id, "{$model->brand->name} {$model->name}");
    }
}
