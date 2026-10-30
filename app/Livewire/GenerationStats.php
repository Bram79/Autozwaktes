<?php

namespace App\Livewire;

use App\Models\Generation;
use App\Site\StatsService;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Top defects of a generation with a build year and fuel filter.
 * The first render happens on the server, so the table is there without JavaScript.
 */
class GenerationStats extends Component
{
    public int $generationId;

    #[Url(as: 'jaar', except: '')]
    public string $year = '';

    #[Url(as: 'brandstof', except: '')]
    public string $fuel = '';

    public function render(StatsService $stats)
    {
        $generation = Generation::with('carModel.brand')->findOrFail($this->generationId);
        $years = $stats->years($generation);
        $fuels = $stats->fuels($generation);

        // ignore filter values that don't exist (e.g. from a hand-edited url)
        $year = isset($years[(int) $this->year]) ? (int) $this->year : null;
        $fuel = in_array($this->fuel, $fuels, true) ? $this->fuel : null;

        return view('livewire.generation-stats', [
            'years' => $years,
            'fuels' => $fuels,
            'selectedYear' => $year,
            'selectedFuel' => $fuel,
            'result' => $stats->topDefects($generation, $year, $fuel),
            'brandName' => $generation->carModel->brand->name,
            'modelName' => $generation->carModel->name,
            'yearLabel' => (string) ($year ?? ''),
        ]);
    }
}
