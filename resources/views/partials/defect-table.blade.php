{{-- $result comes from StatsService::topDefects() --}}
@if (! $result['enough_data'])
    <p class="rounded border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        Voor deze selectie zijn te weinig voertuigen ({{ number_format($result['total'], 0, ',', '.') }}) om betrouwbare percentages te tonen.
    </p>
@else
    @php
        $max = max(1, $result['defects']->max('percentage'));
        $partsLinksLeft = (int) config('site.parts_links_per_table');
    @endphp

    <p class="mb-3 text-slate-700">
        Gebaseerd op {{ number_format($result['total'], 0, ',', '.') }} voertuigen. Het percentage is het aandeel voertuigen waarbij dit gebrek minstens één keer is geconstateerd.
    </p>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm max-sm:block">
            <caption class="sr-only">Meest voorkomende APK-gebreken</caption>
            <thead class="max-sm:sr-only">
                <tr class="border-b-2 border-accent-400">
                    <th scope="col" class="py-2 pr-2">#</th>
                    <th scope="col" class="py-2 pr-4">Gebrek</th>
                    <th scope="col" class="py-2 pr-2 sm:pr-4">Aandeel</th>
                    <th scope="col" class="py-2 text-right">Gemiddeld*</th>
                </tr>
            </thead>
            <tbody class="max-sm:block">
                @foreach ($result['defects'] as $i => $defect)
                    <tr class="border-b border-brand-100 align-top transition hover:bg-brand-50 max-sm:grid max-sm:grid-cols-[1.75rem_minmax(0,1fr)] max-sm:py-3">
                        <td class="py-3 pr-2 text-slate-600 max-sm:py-0 max-sm:font-semibold">{{ $i + 1 }}</td>
                        <td class="py-3 pr-2 sm:pr-4 max-sm:py-0 max-sm:text-base max-sm:font-medium">
                            {{ \App\Site\DefectText::plain($defect->description) }}
                            @if ($partsLinksLeft > 0 && ($brandName ?? null) && ($category = \App\Models\PartsCategory::forDefect($defect->description)))
                                @php $partsLinksLeft--; @endphp
                                <a href="{{ \App\Site\AffiliateLink::url('parts', 'gebrek: '.$category['name'], $brandName, $modelName ?? '', $yearLabel ?? '', $category['id']) }}"
                                   rel="sponsored nofollow noopener" target="_blank" class="mt-1 block py-1 text-sm font-medium text-brand-700 underline underline-offset-2 hover:text-brand-900">Onderdelen: {{ \Illuminate\Support\Str::lower($category['name']) }} (partnerlink)</a>
                            @endif
                        </td>
                        <td class="py-3 pr-2 sm:pr-4 max-sm:col-start-2 max-sm:py-0 max-sm:pr-0 max-sm:pt-2">
                            <div class="flex items-center gap-2">
                                <div class="bar-wrap h-3 min-w-0 flex-1 overflow-hidden rounded-full bg-brand-100 sm:w-40 sm:flex-none" aria-hidden="true">
                                    <div class="bar h-3 rounded-full bg-brand-600" style="width: {{ round($defect->percentage / $max * 100) }}%"></div>
                                </div>
                                <span class="font-semibold tabular-nums">{{ number_format($defect->percentage, 1, ',', '.') }}%</span>
                            </div>
                        </td>
                        <td class="py-3 text-right tabular-nums text-slate-700 max-sm:col-start-2 max-sm:py-0 max-sm:pt-1 max-sm:text-left">
                            @if ($defect->average !== null)
                                <span class="sm:hidden">Gemiddeld: </span>{{ number_format($defect->average, 1, ',', '.') }}%
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

    <x-dropdown title="Koopchecklist" id="checklist">
        <p class="mt-3 text-sm text-slate-700">Let hierop bij het bekijken van een occasion:</p>
        <ul class="mt-3 space-y-2">
            @foreach ($result['defects']->take(5) as $defect)
                <li class="flex gap-2">
                    <span aria-hidden="true">☐</span>
                    <span>Controleer: {{ \Illuminate\Support\Str::lcfirst(\App\Site\DefectText::plain($defect->description)) }}</span>
                </li>
            @endforeach
        </ul>
    </x-dropdown>
@endif
