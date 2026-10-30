<x-filament-panels::page>
    <div class="flex flex-wrap items-center gap-3">
        <label for="dagen" class="text-sm font-medium">Periode</label>
        <select id="dagen" wire:model.live="days" class="rounded-lg border-gray-300 text-sm dark:bg-gray-800">
            <option value="7">Laatste 7 dagen</option>
            <option value="30">Laatste 30 dagen</option>
            <option value="90">Laatste 90 dagen</option>
        </select>
        <p class="text-sm text-gray-600 dark:text-gray-300">Totaal: <strong>{{ number_format($total, 0, ',', '.') }}</strong> klikken. We bewaren geen IP-adres of andere persoonsgegevens.</p>
    </div>

    @if ($total === 0)
        <x-filament::section>
            <p class="text-sm">Nog geen klikken. Zodra je een affiliate-link invult onder <em>Instellingen › Affiliate-instellingen</em> en iemand erop klikt, zie je dat hier.</p>
        </x-filament::section>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            @foreach ([['Per partner', $partners, 'partner'], ['Per pagina', $pages, 'page'], ['Per plek op de pagina', $positions, 'position']] as [$title, $rows, $key])
                <x-filament::section :heading="$title">
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($rows as $row)
                                <tr class="border-b border-gray-100 last:border-0 dark:border-white/10">
                                    <td class="py-2 pr-2 break-all">{{ $key === 'partner' ? ($labels[$row->partner] ?? $row->partner) : $row->$key }}</td>
                                    <td class="py-2 text-right font-semibold">{{ number_format($row->clicks, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
