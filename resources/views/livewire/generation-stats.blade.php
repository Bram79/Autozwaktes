<div>
    <form method="get" class="mb-6 flex flex-wrap items-end gap-4 rounded border border-slate-200 bg-slate-50 p-4">
        <div>
            <label for="jaar" class="block text-sm font-medium">Bouwjaar</label>
            <select id="jaar" name="jaar" wire:model.live="year" class="mt-1 rounded border border-slate-400 bg-white px-3 py-2 focus:border-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-800">
                <option value="">Alle bouwjaren</option>
                @foreach ($years as $y => $total)
                    <option value="{{ $y }}" @selected($selectedYear === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="brandstof" class="block text-sm font-medium">Brandstof</label>
            <select id="brandstof" name="brandstof" wire:model.live="fuel" class="mt-1 rounded border border-slate-400 bg-white px-3 py-2 focus:border-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-800">
                <option value="">Alle brandstoffen</option>
                @foreach ($fuels as $f)
                    <option value="{{ $f }}" @selected($selectedFuel === $f)>{{ $f }}</option>
                @endforeach
            </select>
        </div>
        {{-- without JavaScript the filter is applied with this button --}}
        <noscript><button type="submit" class="rounded bg-blue-800 px-4 py-2 font-medium text-white">Filter toepassen</button></noscript>
    </form>

    <div wire:loading.class="opacity-50">
        @include('partials.defect-table', ['result' => $result])
    </div>
</div>
