<x-layouts.app
    title="Auto Zwaktes: zwakke punten en APK-problemen per automodel"
    description="Zoek je auto en zie welke APK-gebreken het vaakst voorkomen per merk, model, generatie en bouwjaar. Handig bij het kopen van een occasion.">

    <x-slot:hero>
        <section class="relative overflow-hidden bg-gradient-to-br from-brand-900 via-brand-700 to-brand-600 text-white">
            {{-- Zwevende vormen: puur decoratief --}}
            <div class="pointer-events-none absolute -right-16 -top-16 size-72 animate-float rounded-full bg-accent-400/90 blur-sm" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/4 size-64 animate-float-slow rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute bottom-10 right-1/4 size-16 animate-float rounded-2xl border-4 border-accent-300 rotate-12" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-5xl px-4 py-16 sm:py-24">
                <p class="reveal inline-block rounded-full bg-accent-400 px-4 py-1 text-sm font-bold text-brand-900">Gebaseerd op RDW open data</p>
                <h1 class="reveal mt-5 max-w-3xl text-4xl font-extrabold tracking-tight text-accent-300 sm:text-6xl" style="--i: 1">
                    Zwakke punten per automodel
                </h1>
                <p class="reveal mt-5 max-w-2xl text-lg text-brand-100" style="--i: 2">Weet waar jouw volgende auto mee zit. Zoek een merk of model en zie welke APK-gebreken het vaakst voorkomen, per generatie en bouwjaar.</p>

                <div class="reveal mt-8 max-w-xl" style="--i: 3">
                    <livewire:search-box />
                </div>
            </div>
        </section>
    </x-slot:hero>

    @if ($popular->isNotEmpty())
        <section aria-labelledby="populair">
            <h2 id="populair" class="reveal text-2xl font-extrabold text-brand-900">Populaire modellen</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                @foreach ($popular as $model)
                    <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                        <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="card-link">
                            {{ $model->brand->name }} {{ $model->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
