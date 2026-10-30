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

    <h1 class="text-3xl sm:text-4xl">{{ $name }} uit {{ $year }}: APK-gebreken</h1>

    @if ($year > \App\Site\RankingService::lastInspectedYear())
        <p class="mt-4 rounded-2xl border border-accent-400 bg-accent-300/20 p-4 text-sm text-brand-900">
            Auto's van bouwjaar {{ $year }} hebben nog geen APK gehad (de eerste keuring is na {{ config('site.first_apk_after_years') }} jaar). Wat je hieronder ziet, zijn dus nog geen gebreken van een eerste APK en de cijfers zijn nog niet te vergelijken met oudere auto's.
        </p>
    @endif

    @if (\App\Site\PlateVisit::active())
        <x-plate-cta :brand="$model->brand->name" :model="$model->name" :year="$year" />
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @include('partials.defect-table', ['result' => $result, 'brandName' => $model->brand->name, 'modelName' => $model->name, 'yearLabel' => (string) $year])

            @include('partials.data-advice', ['rows' => app(\App\Site\AdviceWriter::class)->rows($generation, $year)])

            <p class="mt-6"><a href="{{ $genUrl }}" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">Alle bouwjaren van de {{ $name }}</a></p>
        </div>
        <div class="space-y-4">
            {{-- after a plate search the report is already shown above, so not a second time --}}
            @unless (\App\Site\PlateVisit::active())
                <x-affiliate-block type="vehicle_report" :brand="$model->brand->name" :model="$model->name" :year="$year" position="zijbalk" />
            @endunless
            <x-ad-slot name="zijbalk" />
            <x-affiliate-block type="insurance" :brand="$model->brand->name" :model="$model->name" :year="$year" />
        </div>
    </div>

    <section class="mt-10" aria-labelledby="jaren">
        <h2 id="jaren" class="text-xl font-bold">Andere bouwjaren</h2>
        <ul class="mt-3 flex flex-wrap gap-2">
            @foreach ($years as $y => $total)
                @if ($y !== $year)
                    <li><a href="{{ route('year', [$model->brand, $model->slug, $generation->slug, $y]) }}" class="chip">{{ $y }}</a></li>
                @endif
            @endforeach
        </ul>
    </section>
</x-layouts.app>
