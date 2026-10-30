<x-layouts.app
    :title="$brand->name.': zwakke punten en APK-problemen per model'"
    :description="'Alle '.$brand->name.'-modellen met de meest voorkomende APK-gebreken per generatie en bouwjaar.'"
    :robots="$models->isEmpty() ? 'noindex,follow' : null">

    <x-breadcrumbs :items="[['Home', route('home')], [$brand->name, route('brand', $brand)]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $brand->name }}: modellen</h1>

    <p class="mt-3 max-w-2xl text-slate-700">Kies een model om de generaties en de meest voorkomende APK-gebreken te zien.</p>

    @if ($models->isEmpty())
        <p class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-5 text-slate-700">Voor dit merk zijn nog geen modellen beschikbaar. <a href="{{ route('rankings') }}" class="font-medium text-brand-700 underline underline-offset-2">Bekijk de ranglijsten</a> of zoek een ander merk.</p>
    @endif

    <ul class="mt-6 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
        @foreach ($models as $model)
            <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                <a href="{{ route('model', [$brand, $model->slug]) }}" class="card-link group flex items-center gap-2">
                    <span>{{ $brand->name }} {{ $model->name }}</span>
                    <span class="card-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
