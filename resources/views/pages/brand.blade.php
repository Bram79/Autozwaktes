<x-layouts.app
    :title="$brand->name.': zwakke punten en APK-problemen per model'"
    :description="'Alle '.$brand->name.'-modellen met de meest voorkomende APK-gebreken per generatie en bouwjaar.'">

    <x-breadcrumbs :items="[['Home', route('home')], [$brand->name, route('brand', $brand)]]" />

    <h1 class="text-3xl font-bold">{{ $brand->name }}: modellen</h1>

    <ul class="mt-6 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
        @foreach ($models as $model)
            <li>
                <a href="{{ route('model', [$brand, $model->slug]) }}" class="block rounded border border-slate-200 px-3 py-2 text-blue-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-800">
                    {{ $brand->name }} {{ $model->name }}
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
