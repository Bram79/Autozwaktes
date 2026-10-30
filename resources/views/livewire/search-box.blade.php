<div>
    <form method="get" action="{{ route('home') }}" role="search" class="flex gap-2">
        <label for="zoek" class="sr-only">Zoek een merk of model</label>
        <input id="zoek" name="q" type="search" wire:model.live.debounce.250ms="query" autocomplete="off"
               placeholder="Bijvoorbeeld: audi a4 of polo"
               class="w-full rounded-full border-2 border-white/0 bg-white px-5 py-3 text-brand-900 shadow-lg placeholder:text-slate-500 focus:border-accent-400 focus:outline-none focus:ring-4 focus:ring-accent-300/60">
        <button type="submit" class="btn">Zoek</button>
    </form>

    @if (trim($query) !== '')
        <div class="mt-3" aria-live="polite">
            @forelse ($results as $model)
                @if ($loop->first)<ul class="divide-y divide-brand-100 overflow-hidden rounded-2xl bg-white shadow-lg">@endif
                <li>
                    <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="block px-4 py-3 text-brand-700 hover:bg-accent-300 focus-visible:bg-accent-300">
                        {{ $model->brand->name }} {{ $model->name }}
                    </a>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="text-sm font-medium text-brand-100">Geen resultaten voor "{{ $query }}".</p>
            @endforelse
        </div>
    @endif
</div>
