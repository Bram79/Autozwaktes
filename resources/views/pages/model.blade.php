@php $name = \App\Site\Seo::modelName($model); @endphp
<x-layouts.app
    :title="$name.': generaties, zwakke punten en APK-problemen'"
    :description="'Overzicht van alle generaties van de '.$name.' met de meest voorkomende APK-gebreken.'">

    <x-breadcrumbs :items="[['Home', route('home')], [$model->brand->name, route('brand', $model->brand)], [$model->name, route('model', [$model->brand, $model->slug])]]" />

    <h1 class="text-3xl font-bold">{{ $name }}: generaties</h1>

    @if ($generations->isEmpty())
        <p class="mt-6 text-slate-700">Voor dit model zijn nog geen cijfers beschikbaar.</p>
    @else
        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($generations as $generation)
                <li>
                    <a href="{{ route('generation', [$model->brand, $model->slug, $generation->slug]) }}" class="block rounded border border-slate-200 p-4 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-800">
                        <span class="block font-semibold text-blue-800">{{ $name }} {{ $generation->code }}</span>
                        <span class="text-sm text-slate-700">{{ \App\Site\Seo::yearsLabel($generation) }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($similar->isNotEmpty())
        <section class="mt-10" aria-labelledby="meer">
            <h2 id="meer" class="text-xl font-bold">Andere {{ $model->brand->name }}-modellen</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($similar as $other)
                    <li><a href="{{ route('model', [$model->brand, $other->slug]) }}" class="rounded border border-slate-200 px-3 py-1 text-blue-800 hover:bg-slate-50">{{ $other->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
