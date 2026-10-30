<x-layouts.app
    :title="$brand->name.': zwakke punten en APK-problemen per model'"
    :description="'Alle '.$brand->name.'-modellen met de meest voorkomende APK-gebreken per generatie en bouwjaar.'"
    :robots="$models->isEmpty() ? 'noindex,follow' : null">

    <x-breadcrumbs :items="[['Home', route('home')], [$brand->name, route('brand', $brand)]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $brand->name }}: modellen</h1>

    <ul class="mt-6 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
        @foreach ($models as $model)
            <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                <a href="{{ route('model', [$brand, $model->slug]) }}" class="card-link">
                    {{ $brand->name }} {{ $model->name }}
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
