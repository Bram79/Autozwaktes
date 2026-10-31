<?php

namespace App\Livewire;

use App\Models\CarModel;
use App\Site\RateGate;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Search on brand and model. Works without JavaScript too (plain GET form). */
class SearchBox extends Component
{
    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** True when this visitor searched too often in the last minute (see config site.limits). */
    public bool $tooFast = false;

    /** The text we search on: a very long query is cut off. */
    public function searchText(): string
    {
        return mb_substr(trim($this->query), 0, 60);
    }

    /** @return Collection<int, CarModel> */
    public function results(): Collection
    {
        $this->tooFast = false;
        $words = preg_split('/\s+/', $this->searchText(), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return collect();
        }

        if (! RateGate::attempt('search:'.request()->ip(), (int) config('site.limits.search_per_minute'))) {
            $this->tooFast = true;

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
