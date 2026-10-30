<div>
    <form method="get" action="{{ route('home') }}" role="search" class="flex gap-2">
        <label for="zoek" class="sr-only">Zoek een merk of model</label>
        <input id="zoek" name="q" type="search" wire:model.live.debounce.250ms="query" autocomplete="off"
               placeholder="Bijvoorbeeld: audi a4 of polo"
               class="w-full rounded border border-slate-400 px-3 py-2 focus:border-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-800">
        <button type="submit" class="rounded bg-blue-800 px-4 py-2 font-medium text-white hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-800 focus:ring-offset-2">Zoek</button>
    </form>

    @if (trim($query) !== '')
        <div class="mt-3" aria-live="polite">
            @forelse ($results as $model)
                @if ($loop->first)<ul class="divide-y divide-slate-200 rounded border border-slate-200">@endif
                <li>
                    <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="block px-3 py-2 text-blue-800 hover:bg-slate-50 focus:bg-slate-50">
                        {{ $model->brand->name }} {{ $model->name }}
                    </a>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="text-sm text-slate-700">Geen resultaten voor "{{ $query }}".</p>
            @endforelse
        </div>
    @endif
</div>
