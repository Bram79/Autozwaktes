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
    :description="'De '.$nameA.' en de '.$nameB.' vergeleken op APK-gebreken, bouwjaar '.$first.'–'.$last.': '.$fmt($scoreA).' tegen '.$fmt($scoreB).' gebreken per 100 auto\'s en de meest voorkomende gebreken naast elkaar.'"
    :canonical="$url">

    <x-breadcrumbs :items="[['Home', route('home')], ['Ranglijsten', route('rankings')], [$nameA.' vs '.$nameB, $url]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $nameA }} vs {{ $nameB }}: APK-gebreken vergeleken</h1>

    <div class="mt-6 max-w-3xl space-y-3 text-slate-800">
        <p>
            We vergelijken de bouwjaren {{ $first }} tot en met {{ $last }}, de jaren waarin beide auto's veel werden gebouwd.
            De {{ $nameA }} heeft in deze jaren gemiddeld {{ $fmt($scoreA) }} geconstateerde APK-gebreken per 100 auto's, de {{ $nameB }} {{ $fmt($scoreB) }}.
            @if ($better)
                Een lager getal is beter: in deze vergelijking scoort de {{ $better }} dus beter, met {{ $fmt(abs($scoreA - $scoreB)) }} gebreken minder per 100 auto's.
            @else
                Ze scoren dus precies gelijk.
            @endif
        </p>
        <p class="text-sm text-slate-600">
            Gebaseerd op {{ $fmt($result['models'][$a->id]['total'], 0) }} {{ $a->name }}'s en {{ $fmt($result['models'][$b->id]['total'], 0) }} {{ $b->name }}'s uit de RDW-cijfers.
            We vergelijken alleen auto's die ongeveer evenveel kostten toen ze nieuw waren. Gebreken per 100 auto's: elk soort gebrek telt één keer per auto.
        </p>
    </div>

    <section class="mt-8" aria-labelledby="gebreken">
        <h2 id="gebreken" class="text-xl font-bold">De meest voorkomende gebreken naast elkaar</h2>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-left text-sm">
                <caption class="sr-only">Percentage auto's met dit gebrek, per model</caption>
                <thead>
                    <tr class="border-b-2 border-accent-400">
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
                                <td class="py-3 pr-4 text-right {{ $isBetter ? 'font-bold text-green-800' : '' }} {{ $loop->last ? 'pr-0' : '' }}">
                                    {{ $fmt($defect[$side]) }}%@if ($isBetter) <span class="sr-only">(lager is beter)</span>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-2 text-xs text-slate-600">Het percentage is het aandeel auto's waarbij het gebrek minstens één keer is geconstateerd. Groen: het laagste van de twee.</p>
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
