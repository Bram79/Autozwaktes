@php
    $model = $generation->carModel;
    $name = \App\Site\Seo::generationName($generation);
    $range = \App\Site\Seo::yearsLabel($generation);
    $url = route('generation', [$model->brand, $model->slug, $generation->slug]);
    $dataset = [
        '@context' => 'https://schema.org',
        '@type' => 'Dataset',
        'name' => "APK-gebreken {$name} ({$range})",
        'description' => "Percentage voertuigen met geconstateerde APK-gebreken voor de {$name}, per bouwjaar en brandstof.",
        'url' => $url,
        'inLanguage' => 'nl',
        'license' => 'https://creativecommons.org/publicdomain/zero/1.0/',
        'isBasedOn' => 'https://opendata.rdw.nl',
        'temporalCoverage' => '2022/..',
    ];
@endphp
<x-layouts.app
    :title="$name.' ('.$range.'): zwakke punten en APK-problemen'"
    :description="'Welke APK-gebreken komen het vaakst voor bij de '.$name.' ('.$range.')? Bekijk de top 10 met percentages, een koopchecklist en het verschil per bouwjaar.'"
    :canonical="$url">

    <x-slot:head>
        <script type="application/ld+json">{!! json_encode($dataset, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    </x-slot:head>

    <x-breadcrumbs :items="[
        ['Home', route('home')],
        [$model->brand->name, route('brand', $model->brand)],
        [$model->name, route('model', [$model->brand, $model->slug])],
        [$generation->code, $url],
    ]" />

    <h1 class="text-3xl font-bold">{{ $name }} ({{ $range }}): zwakke punten en APK-problemen</h1>

    <div class="mt-8 grid gap-8 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <h2 class="mb-3 text-xl font-bold">Top {{ config('site.top_defects') }} APK-gebreken</h2>
            <livewire:generation-stats :generation-id="$generation->id" />

            @if (filled($generation->buying_advice))
                <section class="mt-8" aria-labelledby="advies">
                    <h2 id="advies" class="text-xl font-bold">Koopadvies</h2>
                    <div class="mt-2 space-y-3">{!! nl2br(e($generation->buying_advice)) !!}</div>
                </section>
            @endif
        </div>

        <div class="space-y-4">
            <x-affiliate-block type="vehicle_report" :brand="$model->brand->name" :model="$model->name" />
            <x-affiliate-block type="insurance" :brand="$model->brand->name" :model="$model->name" />
            <x-affiliate-block type="parts" :brand="$model->brand->name" :model="$model->name" />
        </div>
    </div>

    @if ($years)
        <section class="mt-10" aria-labelledby="jaren">
            <h2 id="jaren" class="text-xl font-bold">Per bouwjaar</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($years as $y => $total)
                    <li><a href="{{ route('year', [$model->brand, $model->slug, $generation->slug, $y]) }}" class="rounded border border-slate-200 px-3 py-1 text-blue-800 hover:bg-slate-50">{{ $name }} {{ $y }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($siblings->isNotEmpty())
        <section class="mt-8" aria-labelledby="generaties">
            <h2 id="generaties" class="text-xl font-bold">Andere generaties</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($siblings as $other)
                    <li><a href="{{ route('generation', [$model->brand, $model->slug, $other->slug]) }}" class="rounded border border-slate-200 px-3 py-1 text-blue-800 hover:bg-slate-50">{{ $model->name }} {{ $other->code }} ({{ \App\Site\Seo::yearsLabel($other) }})</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($similar->isNotEmpty())
        <section class="mt-8" aria-labelledby="vergelijkbaar">
            <h2 id="vergelijkbaar" class="text-xl font-bold">Andere {{ $model->brand->name }}-modellen</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($similar as $other)
                    <li><a href="{{ route('model', [$model->brand, $other->slug]) }}" class="rounded border border-slate-200 px-3 py-1 text-blue-800 hover:bg-slate-50">{{ $other->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
