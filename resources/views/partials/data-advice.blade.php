{{-- $rows comes from AdviceWriter::rows(). In a dropdown (<x-dropdown>). --}}
@if (! empty($rows))
    <x-dropdown title="Wat de cijfers zeggen" id="cijfers">
        <table class="w-full text-left">
            <caption class="sr-only">Wat de cijfers zeggen</caption>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="border-b border-brand-100 last:border-0 align-top">
                        <th scope="row" class="w-2/5 py-3 pr-4 font-bold text-brand-900 sm:w-1/4">{{ $row['label'] }}</th>
                        <td class="space-y-1 py-3 {{ $row['tone'] === 'bad' ? 'text-red-900' : ($row['tone'] === 'good' ? 'text-green-900' : '') }}">
                            @foreach ($row['lines'] as $line)
                                <p>{{ $line }}</p>
                            @endforeach
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <p class="mt-2 text-sm text-slate-600">Automatisch opgesteld uit RDW-cijfers. Zie het als een eerste indruk, niet als een keuring.</p>
    </x-dropdown>
@endif
