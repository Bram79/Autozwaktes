@php
    $title = match ($scope) {
        'merk' => "Minste APK-gebreken bij {$label}: ranglijst per generatie",
        'brandstof' => ucfirst(\App\Site\Seo::fuelCars($fuel)).' met de minste APK-gebreken: ranglijst',
        'bouwjaar' => "Occasions {$label} met de minste APK-gebreken",
        default => "Auto's met de minste APK-gebreken: ranglijst per generatie",
    };
    $count = count($rows);
    $maxIndex = max(1, $count ? max(array_column($rows, 'index')) : 1);
    $lastYear = \App\Site\RankingService::lastInspectedYear();
    $firstYear = \App\Site\RankingService::firstRankedYear();
    $intro = match ($scope) {
        'merk' => "Hoe scoren de {$label}-generaties bij de APK? Deze lijst rangschikt ze op hun APK-gebreken, vergeleken met andere auto's van dezelfde bouwjaren.",
        'brandstof' => 'Welke '.\App\Site\Seo::fuelCars($fuel).' hebben bij de APK het minst vaak gebreken? Dit zijn de generaties met de minste en de meeste gebreken.',
        'bouwjaar' => "Kijk je naar een occasion {$label}? Dit zijn de generaties met de minste en de meeste APK-gebreken in dat bouwjaar.",
        default => "De generaties met de minste en de meeste APK-gebreken, van alle auto's op deze site.",
    };
@endphp
<x-layouts.app
    :title="$title.' | '.config('site.name')"
    :description="$intro.' Gebaseerd op '.number_format(array_sum(array_column($rows, 'total')), 0, ',', '.').' voertuigen uit de RDW-cijfers.'"
    :canonical="$url"
    :robots="$count < 5 ? 'noindex,follow' : null">

    @if ($count > 0)
        <x-slot:head>
            <x-json-ld :data="\App\Site\Schema::itemList($title, array_map(
                fn ($row) => [
                    'name' => trim($row['brand'].' '.$row['model'].' '.($row['code'] === \App\Site\Seo::ALL_YEARS ? '' : $row['code'])),
                    'url' => route('generation', [$row['brand_slug'], $row['model_slug'], $row['generation_slug']]),
                ],
                array_slice($rows, 0, 10),
            ))" />
        </x-slot:head>
    @endif

    <x-breadcrumbs :items="[['Home', route('home')], ['Ranglijsten', route('rankings')], [$label !== '' ? ucfirst($label) : 'Alles', $url]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $title }}</h1>
    <p class="mt-3 max-w-2xl text-ink-soft">{{ $intro }}</p>

    @if ($count === 0)
        <div class="mt-8 panel text-ink-soft">
            <p class="font-semibold text-brand-900">Nog geen lijst</p>
            <p class="mt-1 text-sm">Voor deze lijst zijn nog te weinig auto's om een eerlijke vergelijking te maken.</p>
            <p class="mt-3"><a href="{{ route('ranking', ['alles']) }}" class="btn">Bekijk de lijst met alle auto's</a></p>
        </div>
    @else
        <div class="mt-3 max-w-2xl space-y-1 text-sm text-ink-soft">
            <p><strong>Score: 100 = gemiddeld.</strong> We vergelijken elke generatie met het gemiddelde van alle auto's op deze site van dezelfde bouwjaren en brandstof. Een score van 80 is dus 20% beter dan gemiddeld, 120 is 20% slechter. Zo hoeft een oudere auto niet te boeten voor zijn leeftijd. Lager is beter; de balk toont de score ten opzichte van de hoogste in deze lijst.</p>
            <p>De score zegt niets over kilometers, onderhoud of gebruik (bijvoorbeeld als bedrijfswagen). Zie het als een indicatie, niet als een oordeel over de auto.</p>
            <p>Alleen auto's van bouwjaar {{ $firstYear }} tot en met bouwjaar {{ $lastYear }} tellen mee: nieuwere auto's hebben nog geen APK gehad en lijken daardoor onterecht foutloos, en van veel oudere auto's blijven vooral de best onderhouden exemplaren over. Alleen generaties met minstens {{ number_format(config('site.min_ranking_sample'), 0, ',', '.') }} auto's doen mee.</p>
        </div>

        @foreach ([['De minste gebreken', 'minste', $best], ['De meeste gebreken', 'meeste', $worst]] as [$heading, $id, $list])
            @if ($list !== [] && ($id === 'minste' || $count > count($best)))
                <section class="mt-8" aria-labelledby="{{ $id }}">
                    <h2 id="{{ $id }}" class="text-xl font-semibold">{{ $heading }}</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">{{ $heading }}</caption>
                            <thead>
                                <tr class="border-b border-brand-600">
                                    <th scope="col" class="py-2 pr-2">#</th>
                                    <th scope="col" class="py-2 pr-4">Auto</th>
                                    <th scope="col" class="hidden py-2 pr-4 sm:table-cell">Bouwjaren</th>
                                    <th scope="col" class="py-2 pr-4 text-right">Score (100 = gemiddeld)</th>
                                    <th scope="col" class="py-2 text-right">Auto's</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($list as $i => $row)
                                    <tr class="border-b border-brand-100 transition hover:bg-brand-50">
                                        <td class="py-3 pr-2 text-ink-soft">{{ $id === 'minste' ? $i + 1 : $count - $i }}</td>
                                        <td class="py-3 pr-4">
                                            <a class="font-medium text-brand-700 underline underline-offset-2 hover:text-brand-900" href="{{ $year ? route('year', [$row['brand_slug'], $row['model_slug'], $row['generation_slug'], $year]) : route('generation', [$row['brand_slug'], $row['model_slug'], $row['generation_slug']]) }}">
                                                {{ $row['brand'] }} {{ $row['model'] }} {{ $row['code'] === \App\Site\Seo::ALL_YEARS ? '' : $row['code'] }}
                                            </a>
                                            @if ($id === 'minste' && $i === 0)<span class="badge-best ml-2 align-middle">Beste score</span>@endif
                                            <span class="block text-xs text-ink-soft sm:hidden">{{ $year ?? $row['years'] }}</span>
                                        </td>
                                        <td class="hidden py-3 pr-4 text-ink-soft sm:table-cell">{{ $year ?? $row['years'] }}</td>
                                        <td class="min-w-28 py-3 pr-4 text-right">
                                            <span class="font-semibold tabular-nums" data-count="{{ $row['index'] }}">{{ number_format($row['index'], 0, ',', '.') }}</span>
                                            <span class="block text-xs text-ink-soft">{{ round(abs($row['index'] - 100)) === 0.0 ? 'gemiddeld' : round(abs($row['index'] - 100)).'% '.($row['index'] < 100 ? 'beter' : 'slechter') }}</span>
                                            <span class="score-track mt-1 block" aria-hidden="true"><span class="bar score-fill {{ $id === 'minste' ? 'score-fill-best' : '' }}" style="--w: {{ round($row['index'] / $maxIndex * 100) }}%"></span></span>
                                        </td>
                                        <td class="py-3 text-right tabular-nums text-ink-soft">{{ number_format($row['total'], 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        @endforeach
    @endif

    @include('partials.ranking-links', ['brands' => $brands, 'fuels' => $fuels, 'years' => $years])
</x-layouts.app>
