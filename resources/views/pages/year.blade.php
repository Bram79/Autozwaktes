@php
    $model = $generation->carModel;
    $name = \App\Site\Seo::generationName($generation);
    $url = route('year', [$model->brand, $model->slug, $generation->slug, $year]);
    $genUrl = route('generation', [$model->brand, $model->slug, $generation->slug]);
@endphp
<x-layouts.app
    :title="$name.' ('.$year.'): APK-gebreken en zwakke punten'"
    :description="'De meest voorkomende APK-gebreken bij de '.$name.' uit '.$year.', met percentages en een koopchecklist.'"
    :canonical="$url">

    <x-breadcrumbs :items="[
        ['Home', route('home')],
        [$model->brand->name, route('brand', $model->brand)],
        [$model->name, route('model', [$model->brand, $model->slug])],
        [$generation->code, $genUrl],
        [(string) $year, $url],
    ]" />

    <h1 class="text-3xl font-bold">{{ $name }} uit {{ $year }}: APK-gebreken</h1>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @include('partials.defect-table', ['result' => $result])

            <p class="mt-6"><a href="{{ $genUrl }}" class="text-blue-800 underline underline-offset-2">Alle bouwjaren van de {{ $name }}</a></p>
        </div>
        <div class="space-y-4">
            <x-affiliate-block type="vehicle_report" :brand="$model->brand->name" :model="$model->name" :year="$year" />
            <x-affiliate-block type="insurance" :brand="$model->brand->name" :model="$model->name" :year="$year" />
        </div>
    </div>

    <section class="mt-10" aria-labelledby="jaren">
        <h2 id="jaren" class="text-xl font-bold">Andere bouwjaren</h2>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($years as $y => $total)
                @if ($y !== $year)
                    <li><a href="{{ route('year', [$model->brand, $model->slug, $generation->slug, $y]) }}" class="rounded border border-slate-200 px-3 py-1 text-blue-800 hover:bg-slate-50">{{ $y }}</a></li>
                @endif
            @endforeach
        </ul>
    </section>
</x-layouts.app>
