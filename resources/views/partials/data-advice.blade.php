{{-- $rows comes from AdviceWriter::rows(). A dropdown that works without JavaScript (<details>), closed until the visitor opens it. --}}
@if (! empty($rows))
    <details class="group mt-8 rounded-2xl border border-brand-100 bg-brand-50">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 rounded-2xl p-5 [&::-webkit-details-marker]:hidden">
            <h2 id="cijfers" class="text-xl font-bold">Wat de cijfers zeggen</h2>
            <svg class="size-5 shrink-0 text-brand-700 transition-transform group-open:rotate-180" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.22 7.22a.75.75 0 0 1 1.06 0L10 10.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 8.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
            </svg>
        </summary>

        <div class="border-t border-brand-100 px-5 pb-5">
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
        </div>
    </details>
@endif
