{{-- $paragraphs comes from AdviceWriter::paragraphs() --}}
@if (! empty($paragraphs))
    <section class="mt-8 rounded-2xl border border-brand-100 bg-brand-50 p-5" aria-labelledby="cijfers">
        <h2 id="cijfers" class="text-xl font-bold">Wat de cijfers zeggen</h2>
        <div class="mt-2 space-y-3">
            @foreach ($paragraphs as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-slate-600">Automatisch opgesteld uit RDW-cijfers. Zie het als een eerste indruk, niet als een keuring.</p>
    </section>
@endif
