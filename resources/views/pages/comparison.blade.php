@php
    $nameA = \App\Site\Seo::modelName($a);
    $nameB = \App\Site\Seo::modelName($b);
    $first = min($result['years']);
    $last = max($result['years']);
    $scoreA = $result['models'][$a->id]['score'];
    $scoreB = $result['models'][$b->id]['score'];
    $better = $scoreA === $scoreB ? null : ($scoreA < $scoreB ? $nameA : $nameB);
    $fmt = fn ($n, $d = 1) => number_format($n, $d, ',', '.');
@endphp
<x-layouts.app
    :title="$nameA.' vs '.$nameB.': welke heeft de minste APK-gebreken?'"
    :description="'De '.$nameA.' en de '.$nameB.' vergeleken op APK-gebreken, bouwjaren '.$first.'–'.$last.': '.$fmt($scoreA).' tegen '.$fmt($scoreB).' gebreken per 100 auto\'s en de meest voorkomende gebreken naast elkaar.'"
    :canonical="$url">

    <x-breadcrumbs :items="[['Home', route('home')], ['Ranglijsten', route('rankings')], [$nameA.' vs '.$nameB, $url]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $nameA }} vs {{ $nameB }}: APK-gebreken vergeleken</h1>

    @php $maxPair = max($scoreA, $scoreB, 1); @endphp
    <section class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="Score per model">
        @foreach ([[$nameA, $scoreA], [$nameB, $scoreB]] as [$cardName, $cardScore])
            @php $wins = $better === $cardName; @endphp
            <div class="rounded-lg border-2 {{ $wins ? 'border-accent-400 bg-accent-300/20' : 'border-brand-100 bg-brand-50' }} p-4">
                <div class="flex items-center justify-between gap-2">
                    <p class="font-semibold text-brand-900">{{ $cardName }}</p>
                    @if ($wins)<span class="badge-best">Lager</span>@endif
                </div>
                <p class="mt-2 text-3xl font-semibold tabular-nums text-brand-900">{{ $fmt($cardScore) }} <span class="text-sm font-medium text-ink-soft">gebreken per 100 auto's</span></p>
                <div class="score-track mt-3" aria-hidden="true"><span class="bar score-fill {{ $wins ? 'score-fill-best' : '' }}" style="--w: {{ round($cardScore / $maxPair * 100) }}%"></span></div>
            </div>
        @endforeach
    </section>

    <div class="mt-6 max-w-3xl space-y-3 text-brand-900">
        <p>
            We vergelijken de bouwjaren {{ $first }} tot en met {{ $last }}, de jaren waarin beide auto's veel werden gebouwd.
            De {{ $nameA }} heeft in deze jaren gemiddeld {{ $fmt($scoreA) }} geconstateerde APK-gebreken per 100 auto's, de {{ $nameB }} {{ $fmt($scoreB) }}.
            @if ($better)
                Een lager getal betekent minder gebreken: in deze vergelijking komt de {{ $better }} lager uit, met {{ $fmt(abs($scoreA - $scoreB)) }} gebreken minder per 100 auto's.
            @else
                Ze scoren dus precies gelijk.
            @endif
        </p>
        <p class="text-sm text-ink-soft">
            Gebaseerd op {{ $fmt($result['models'][$a->id]['total'], 0) }} auto's van het model {{ $a->name }} en {{ $fmt($result['models'][$b->id]['total'], 0) }} van het model {{ $b->name }}, uit de RDW-cijfers.
            We vergelijken alleen auto's die ongeveer evenveel kostten toen ze nieuw waren. Gebreken per 100 auto's: elk soort gebrek telt één keer per auto, dus een auto kan meerdere gebreken hebben en het getal kan boven de 100 uitkomen.
            Verschillen in kilometers, onderhoud, gebruik en in de verdeling over bouwjaren en brandstof zijn hierin niet verwerkt: zie het als een indicatie.
        </p>
    </div>

    <section class="mt-8" aria-labelledby="gebreken">
        <h2 id="gebreken" class="text-xl font-semibold">De meest voorkomende gebreken naast elkaar</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Percentage auto's met dit gebrek, per model</caption>
                <thead>
                    <tr class="border-b border-brand-600">
                        <th scope="col" class="py-2 pr-4">Gebrek</th>
                        <th scope="col" class="py-2 pr-4 text-right">{{ $nameA }}</th>
                        <th scope="col" class="py-2 text-right">{{ $nameB }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['defects'] as $defect)
                        <tr class="border-b border-brand-100">
                            <td class="py-3 pr-4">{{ \App\Site\DefectText::plain($defect['description']) }}</td>
                            @foreach (['a', 'b'] as $side)
                                @php $isBetter = $defect['a'] !== $defect['b'] && $defect[$side] === min($defect['a'], $defect['b']); @endphp
                                <td class="py-3 pr-4 text-right {{ $isBetter ? 'font-semibold text-green-800' : '' }} {{ $loop->last ? 'pr-0' : '' }}">
                                    {{ $fmt($defect[$side]) }}%@if ($isBetter) <span class="sr-only">(lager is beter)</span>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-ink-soft">Het percentage is het aandeel auto's waarbij het gebrek minstens één keer is geconstateerd. Groen: het laagste van de twee.</p>
    </section>

    <section class="mt-10 grid gap-4 md:grid-cols-2" aria-label="Meer over deze auto's">
        @foreach ([$a, $b] as $model)
            <a href="{{ route('model', [$model->brand, $model->slug]) }}" class="card-link">Alle generaties van de {{ \App\Site\Seo::modelName($model) }}</a>
        @endforeach
    </section>

    <div class="mt-8 grid gap-4 md:grid-cols-2">
        <x-affiliate-block type="vehicle_report" :brand="$a->brand->name" :model="$a->name" position="vergelijking" :prominent="true" />
        <x-affiliate-block type="insurance" :brand="$a->brand->name" :model="$a->name" position="vergelijking" />
    </div>
</x-layouts.app>
