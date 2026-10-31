@php
    // the error page must never fail itself, also not when the database is down
    try {
        $popular = app(\App\Site\StatsService::class)->popularModels(8);
    } catch (\Throwable) {
        $popular = collect();
    }
@endphp
<x-layouts.app :title="'Pagina niet gevonden | '.config('site.name')" description="Deze pagina bestaat niet (meer). Zoek een auto of bekijk de populaire modellen." robots="noindex,follow">
    <div class="max-w-2xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-brand-700">Fout 404</p>
        <h1 class="mt-1 text-3xl sm:text-4xl">Deze pagina bestaat niet</h1>
        <p class="mt-3 text-ink-soft">Misschien is het adres veranderd, of zijn er voor deze auto nog geen cijfers. Zoek hieronder verder, of kies een populair model.</p>

        <div class="mt-6 rounded-lg bg-brand-900 p-5">
            <livewire:search-box />
        </div>

        @if ($popular->isNotEmpty())
            <section class="mt-8" aria-labelledby="populair-404">
                <h2 id="populair-404" class="text-xl font-semibold">Populaire modellen</h2>
                <ul class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($popular as $model)
                        <li><a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="card-link">{{ $model->brand->name }} {{ $model->name }}</a></li>
                    @endforeach
                </ul>
            </section>
        @endif

        <p class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('home') }}" class="btn">Naar de homepage</a>
            <a href="{{ route('rankings') }}" class="btn">Bekijk de ranglijsten</a>
        </p>
    </div>
</x-layouts.app>
