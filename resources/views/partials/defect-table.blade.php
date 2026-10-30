{{-- $result comes from StatsService::topDefects() --}}
@if (! $result['enough_data'])
    <p class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        Voor deze selectie zijn te weinig voertuigen ({{ number_format($result['total'], 0, ',', '.') }}) om betrouwbare percentages te tonen.
    </p>
@else
    @php $max = max(1, $result['defects']->max('percentage')); @endphp

    <p class="mb-3 text-slate-700">
        Gebaseerd op {{ number_format($result['total'], 0, ',', '.') }} voertuigen. Het percentage is het aandeel voertuigen waarbij dit gebrek minstens één keer is geconstateerd.
    </p>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Meest voorkomende APK-gebreken</caption>
            <thead>
                <tr class="border-b-2 border-accent-400">
                    <th scope="col" class="py-2 pr-2">#</th>
                    <th scope="col" class="py-2 pr-4">Gebrek</th>
                    <th scope="col" class="py-2 pr-2 sm:pr-4">Aandeel</th>
                    <th scope="col" class="py-2 text-right">Gemiddeld*</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($result['defects'] as $i => $defect)
                    <tr class="border-b border-brand-100 align-top transition hover:bg-brand-50">
                        <td class="py-3 pr-2 text-slate-600">{{ $i + 1 }}</td>
                        <td class="py-3 pr-2 sm:pr-4">
                            {{ $defect->description }}
                            @if (($category = \App\Models\PartsCategory::forDefect($defect->description)) && ($brandName ?? null))
                                <a href="{{ \App\Site\AffiliateLink::url('parts', 'gebrek: '.$category['name'], $brandName, $modelName ?? '', $yearLabel ?? '', $category['id']) }}"
                                   rel="sponsored nofollow noopener" target="_blank" class="mt-1 block py-1 text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-900">Onderdelen: {{ \Illuminate\Support\Str::lower($category['name']) }} (affiliate)</a>
                            @endif
                        </td>
                        <td class="py-3 pr-2 sm:pr-4">
                            <div class="flex items-center gap-2">
                                <div class="bar-wrap h-3 w-10 shrink-0 overflow-hidden rounded-full bg-brand-100 sm:w-40" aria-hidden="true">
                                    <div class="bar h-3 rounded-full bg-brand-600" style="width: {{ round($defect->percentage / $max * 100) }}%"></div>
                                </div>
                                <span class="font-semibold tabular-nums">{{ number_format($defect->percentage, 1, ',', '.') }}%</span>
                            </div>
                        </td>
                        <td class="py-3 text-right tabular-nums text-slate-700">
                            @if ($defect->average !== null)
                                {{ number_format($defect->average, 1, ',', '.') }}%
                                @if ($defect->percentage > $defect->average * 1.25)
                                    <span class="block text-xs font-medium text-red-800">vaker dan gemiddeld</span>
                                @elseif ($defect->percentage < $defect->average * 0.8)
                                    <span class="block text-xs font-medium text-green-800">minder vaak</span>
                                @endif
                            @else
                                –
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-sm text-slate-600">* Gemiddeld voor de andere modellen op deze site met dezelfde bouwjaren.</p>

    <section class="mt-8" aria-labelledby="checklist">
        <h2 id="checklist" class="text-xl font-bold">Koopchecklist</h2>
        <p class="mt-1 text-sm text-slate-700">Let hierop bij het bekijken van een occasion:</p>
        <ul class="mt-3 space-y-2">
            @foreach ($result['defects']->take(5) as $defect)
                <li class="flex gap-2">
                    <span aria-hidden="true">☐</span>
                    <span>Controleer: {{ \Illuminate\Support\Str::lcfirst($defect->description) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
@endif
