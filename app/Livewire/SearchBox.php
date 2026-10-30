<?php

namespace App\Livewire;

use App\Models\CarModel;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Search on brand and model. Works without JavaScript too (plain GET form). */
class SearchBox extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** @return Collection<int, CarModel> */
    public function results(): Collection
    {
        $words = preg_split('/\s+/', trim($this->query), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return collect();
        }

        $models = CarModel::withStats()->with('brand')->limit(10);

        foreach (array_slice($words, 0, 4) as $word) {
            $like = '%'.addcslashes($word, '%_\\').'%';
            $models->where(fn ($q) => $q
                ->where('name', 'like', $like)
                ->orWhereHas('brand', fn ($b) => $b->where('name', 'like', $like)));
        }

        return $models->get();
    }

    public function render()
    {
        return view('livewire.search-box', ['results' => $this->results()]);
    }
}
