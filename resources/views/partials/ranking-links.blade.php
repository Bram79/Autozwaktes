{{-- Links to the other lists. Needs $brands, $fuels and $years. --}}
<section class="mt-10" aria-labelledby="andere-lijsten">
    <h2 id="andere-lijsten" class="text-xl font-semibold">Andere ranglijsten</h2>

    <p class="mt-3 text-sm font-semibold text-ink-soft">Per merk</p>
    <ul class="mt-2 flex flex-wrap gap-2">
        @foreach ($brands as $b)
            <li><a href="{{ route('ranking', ['merk', $b->slug]) }}" class="chip">{{ $b->name }}</a></li>
        @endforeach
    </ul>

    @if ($fuels)
        <p class="mt-4 text-sm font-semibold text-ink-soft">Per brandstof</p>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach ($fuels as $slug => $name)
                <li><a href="{{ route('ranking', ['brandstof', $slug]) }}" class="chip">{{ $name }}</a></li>
            @endforeach
        </ul>
    @endif

    @if ($years)
        <p class="mt-4 text-sm font-semibold text-ink-soft">Per bouwjaar</p>
        <ul class="mt-2 flex flex-wrap gap-2">
            @foreach ($years as $y)
                <li><a href="{{ route('ranking', ['bouwjaar', $y]) }}" class="chip">{{ $y }}</a></li>
            @endforeach
        </ul>
    @endif
</section>
