@php
    $title = match ($scope) {
        'merk' => "Minste APK-gebreken bij {$label}: ranglijst per generatie",
        'brandstof' => "Auto's op {$label} met de minste APK-gebreken: ranglijst",
        'bouwjaar' => "Betrouwbaarste occasions {$label}: de minste APK-gebreken",
        default => "Auto's met de minste APK-gebreken: ranglijst per generatie",
    };
    $count = count($rows);
    $intro = match ($scope) {
        'merk' => "Hoe scoren de {$label}-generaties bij de APK? Deze lijst rangschikt ze op het aantal gebreken per 100 auto's.",
        'brandstof' => "Welke {$label}-auto's hebben bij de APK het minst vaak gebreken? Dit zijn de beste en de slechtste generaties.",
        'bouwjaar' => "Kijk je naar een occasion {$label}? Dit zijn de generaties met de minste en de meeste APK-gebreken in dat bouwjaar.",
        default => "De generaties met de minste en de meeste APK-gebreken, van alle auto's op deze site.",
    };
@endphp
<x-layouts.app
    :title="$title.' | Auto Zwaktes'"
    :description="$intro.' Gebaseerd op '.number_format(array_sum(array_column($rows, 'total')), 0, ',', '.').' voertuigen uit de RDW-cijfers.'"
    :canonical="$url"
    :robots="$count < 5 ? 'noindex,follow' : null">

    <x-breadcrumbs :items="[['Home', route('home')], ['Ranglijsten', route('rankings')], [$label !== '' ? ucfirst($label) : 'Alles', $url]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $title }}</h1>
    <p class="mt-3 max-w-2xl text-slate-700">{{ $intro }}</p>

    @if ($count === 0)
        <p class="mt-8 rounded-2xl bg-brand-50 p-4 text-sm text-slate-700">Voor deze lijst zijn nog te weinig auto's om een eerlijke vergelijking te maken.</p>
    @else
        <p class="mt-3 max-w-2xl text-sm text-slate-600">Score = geconstateerde APK-gebreken per 100 auto's (elk soort gebrek telt één keer per auto). Alleen generaties met minstens {{ number_format(config('site.min_ranking_sample'), 0, ',', '.') }} auto's doen mee.</p>

        @foreach ([['De minste gebreken', 'minste', $best], ['De meeste gebreken', 'meeste', $worst]] as [$heading, $id, $list])
            @if ($list !== [] && ($id === 'minste' || $count > count($best)))
                <section class="mt-8" aria-labelledby="{{ $id }}">
                    <h2 id="{{ $id }}" class="text-xl font-bold">{{ $heading }}</h2>
                    <div class="mt-3 overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <caption class="sr-only">{{ $heading }}</caption>
                            <thead>
                                <tr class="border-b-2 border-accent-400">
                                    <th scope="col" class="py-2 pr-2">#</th>
                                    <th scope="col" class="py-2 pr-4">Auto</th>
                                    <th scope="col" class="py-2 pr-4">Bouwjaren</th>
                                    <th scope="col" class="py-2 pr-4 text-right">Gebreken per 100</th>
                                    <th scope="col" class="py-2 text-right">Auto's</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($list as $i => $row)
                                    <tr class="border-b border-brand-100 transition hover:bg-brand-50">
                                        <td class="py-3 pr-2 text-slate-600">{{ $id === 'minste' ? $i + 1 : $count - $i }}</td>
                                        <td class="py-3 pr-4">
                                            <a class="font-medium text-brand-700 underline underline-offset-2" href="{{ $year ? route('year', [$row['brand_slug'], $row['model_slug'], $row['generation_slug'], $year]) : route('generation', [$row['brand_slug'], $row['model_slug'], $row['generation_slug']]) }}">
                                                {{ $row['brand'] }} {{ $row['model'] }} {{ $row['code'] }}
                                            </a>
                                        </td>
                                        <td class="py-3 pr-4 text-slate-700">{{ $year ?? $row['years'] }}</td>
                                        <td class="py-3 pr-4 text-right font-semibold">{{ number_format($row['score'], 1, ',', '.') }}</td>
                                        <td class="py-3 text-right text-slate-700">{{ number_format($row['total'], 0, ',', '.') }}</td>
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
