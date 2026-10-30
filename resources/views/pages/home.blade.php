<x-layouts.app
    title="Auto Zwaktes: zwakke punten en APK-problemen per automodel"
    description="Zoek je auto en zie welke APK-gebreken het vaakst voorkomen per merk, model, generatie en bouwjaar. Handig bij het kopen van een occasion.">

    <x-slot:hero>
        <section class="relative overflow-hidden bg-brand-700 bg-gradient-to-br from-brand-900 via-brand-700 to-brand-600 text-white">
            {{-- Zwevende vormen: puur decoratief --}}
            <div class="pointer-events-none absolute -right-24 -top-24 size-48 animate-float sm:-right-16 sm:-top-16 sm:size-72 rounded-full bg-accent-400/90 blur-sm" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-24 left-1/4 size-64 animate-float-slow rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute bottom-10 right-1/4 size-16 animate-float rounded-2xl border-4 border-accent-300 rotate-12" aria-hidden="true"></div>

            <div class="relative mx-auto max-w-5xl px-4 py-8 sm:py-24">
                <p class="reveal inline-block rounded-full bg-accent-400 px-3 py-0.5 text-xs font-bold sm:px-4 sm:py-1 sm:text-sm text-brand-900">Gebaseerd op RDW open data</p>
                <h1 class="reveal mt-3 max-w-3xl text-3xl font-extrabold tracking-tight text-accent-300 sm:mt-5 sm:text-6xl" style="--i: 1">
                    Zwakke punten per automodel
                </h1>
                <p class="reveal mt-5 hidden max-w-2xl text-lg text-brand-100 sm:block" style="--i: 2">Weet waar jouw volgende auto mee zit. Zoek een merk of model en zie welke APK-gebreken het vaakst voorkomen, per generatie en bouwjaar.</p>

                <div class="reveal mt-4 max-w-xl sm:mt-8" style="--i: 3">
                    <livewire:search-box />
                </div>

                <form method="post" action="{{ route('plate') }}" class="reveal mt-4 max-w-xl sm:mt-6" style="--i: 4">
                    @csrf
                    <label for="kenteken" class="block text-sm font-semibold text-brand-100">Of zoek op kenteken</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        {{-- ziet eruit als een Nederlands kenteken --}}
                        <div class="flex w-full sm:max-w-60 overflow-hidden rounded-md border-2 border-brand-900 bg-accent-400 shadow-lg">
                            <span class="flex w-9 items-center justify-center bg-brand-600 text-xs font-bold text-white" aria-hidden="true">NL</span>
                            <input id="kenteken" name="kenteken" type="text" required maxlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                   placeholder="AB-123-C"
                                   class="w-full bg-transparent px-3 py-2 text-lg font-bold uppercase tracking-widest text-brand-900 placeholder:text-brand-900/70 focus:outline-none focus:ring-4 focus:ring-inset focus:ring-brand-900">
                        </div>
                        <button type="submit" class="btn max-sm:w-full">Bekijk mijn auto</button>
                    </div>
                    @if (session('plate_error'))
                        <p role="alert" class="mt-3 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium text-white">{{ session('plate_error') }}</p>
                    @endif
                    <p class="mt-2 text-sm text-brand-100">We zoeken de auto op bij de RDW en slaan je kenteken niet op.</p>
                </form>
            </div>
        </section>
    </x-slot:hero>

    @if ($popular->isNotEmpty())
        <section aria-labelledby="populair">
            <h2 id="populair" class="reveal text-2xl font-extrabold text-brand-900">Populaire modellen</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                @foreach ($popular as $model)
                    <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                        <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="card-link group flex items-center gap-2">
                            <span>
                                <span class="block text-xs font-medium uppercase tracking-wide text-slate-700">{{ $model->brand->name }}</span>
                                <span class="block font-semibold">{{ $model->name }}</span>
                            </span>
                            <span class="card-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="relative mt-12 overflow-hidden rounded-2xl bg-brand-900 p-6 text-white sm:p-8" aria-labelledby="ranglijsten">
        <div class="pointer-events-none absolute -right-10 -top-10 size-32 rounded-full bg-accent-400" aria-hidden="true"></div>
        <h2 id="ranglijsten" class="reveal relative max-w-xl text-2xl font-extrabold text-accent-300">Welke auto heeft de minste gebreken?</h2>
        <p class="reveal relative mt-2 max-w-2xl text-brand-100" style="--i: 1">Bekijk de ranglijsten met de beste en de slechtste generaties, per merk, brandstof en bouwjaar. Hoe lager de score, hoe minder gebreken bij de APK.</p>
        <p class="reveal relative mt-5 flex flex-wrap gap-3" style="--i: 2">
            <a href="{{ route('rankings') }}" class="btn">Naar de ranglijsten</a>
            <a href="{{ route('ranking', ['alles']) }}" class="inline-flex min-h-11 items-center rounded-full border-2 border-white/60 px-5 py-2 font-semibold text-white transition hover:bg-white/10">Direct naar de top</a>
        </p>
    </section>
</x-layouts.app>
