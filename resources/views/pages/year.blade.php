@php
    $model = $generation->carModel;
    $name = \App\Site\Seo::generationName($generation);
    $url = route('year', [$model->brand, $model->slug, $generation->slug, $year]);
    $genUrl = route('generation', [$model->brand, $model->slug, $generation->slug]);
    $topDefect = $result['enough_data'] ? $result['defects']->first() : null;
    // numbers from the data in the description: a page for 2010 differs from a page for 2011 in more than the year
    $description = $topDefect
        ? $name.' uit '.$year.': het vaakst '.\Illuminate\Support\Str::lower(\App\Site\DefectText::plain($topDefect->description)).' ('.number_format($topDefect->percentage, 1, ',', '.').'%), op basis van '.number_format($result['total'], 0, ',', '.').' voertuigen. Met koopchecklist.'
        : 'De meest voorkomende APK-gebreken bij de '.$name.' uit '.$year.', met percentages en een koopchecklist.';
@endphp
<x-layouts.app
    :title="$name.' ('.$year.'): APK-gebreken en zwakke punten'"
    :description="$description"
    :canonical="$url"
    :robots="$year > \App\Site\RankingService::lastInspectedYear() ? 'noindex,follow' : null">

    <x-slot:head>
        <x-json-ld :data="\App\Site\Schema::dataset(
            'APK-gebreken '.$name.' (bouwjaar '.$year.')',
            'Percentage voertuigen met geconstateerde APK-gebreken voor de '.$name.' van bouwjaar '.$year.', per brandstof.',
            $url,
            \App\Site\Seo::modelName($model),
        )" />
    </x-slot:head>

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
            Auto's van bouwjaar {{ $year }} hebben nog geen APK gehad (de eerste keuring is na {{ config('site.first_apk_after_years') }} jaar). Er zijn dus nog geen APK-gebreken om te laten zien. Bekijk de bouwjaren hieronder voor auto's die wel een APK hebben gehad.
        </p>
    @endif

    @if (\App\Site\PlateVisit::forModel($model->id))
        <x-plate-price />
        <x-plate-cta :brand="$model->brand->name" :model="$model->name" :year="\App\Site\PlateVisit::year() ?? $year" />
    @endif

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($year <= \App\Site\RankingService::lastInspectedYear())
                <h2 class="mb-3 text-xl font-bold">Top {{ config('site.top_defects') }} APK-gebreken bij bouwjaar {{ $year }}</h2>
                @include('partials.defect-table', ['result' => $result, 'brandName' => $model->brand->name, 'modelName' => $model->name, 'yearLabel' => (string) $year])

                @include('partials.data-advice', ['rows' => app(\App\Site\AdviceWriter::class)->rows($generation, $year)])
            @endif

            <p class="mt-6"><a href="{{ $genUrl }}" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">Alle bouwjaren van de {{ $name }}</a></p>
        </div>
        <div class="space-y-4">
            {{-- after a plate search the report is already shown above, so not a second time --}}
            @unless (\App\Site\PlateVisit::forModel($model->id))
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
