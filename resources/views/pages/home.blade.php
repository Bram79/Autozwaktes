<x-layouts.app
    :title="config('site.name').': zwakke punten en APK-problemen per automodel'"
    description="Zoek je auto en zie welke APK-gebreken het vaakst voorkomen per merk, model, generatie en bouwjaar. Handig bij het kopen van een occasion."
    :robots="request()->filled('q') ? 'noindex,follow' : null">

    <x-slot:head>
        <x-json-ld :data="\App\Site\Schema::website()" />
        <x-json-ld :data="\App\Site\Schema::organization()" />
    </x-slot:head>

    <x-slot:hero>
        <section class="relative isolate overflow-hidden border-b border-white/10 bg-brand-900 text-white">
            <div class="relative mx-auto max-w-5xl px-4 py-14 sm:py-28">
                <p class="hero-in text-xs font-medium uppercase tracking-[0.2em] text-brand-100">APK-gebreken per automodel</p>
                <h1 class="mt-4 max-w-3xl text-4xl font-semibold leading-[1.1] tracking-tight text-white sm:text-6xl">
                    <span class="hero-mask"><span class="hero-line">Zwakke punten per automodel</span></span>
                </h1>
                <div class="hero-rule mt-6 max-sm:hidden" aria-hidden="true"></div>
                <p class="hero-in mt-6 max-w-2xl text-lg text-brand-100 max-sm:sr-only" style="--d: 0.45">Weet waar jouw volgende auto mee zit. Zoek een merk of model en zie welke APK-gebreken het vaakst voorkomen, per generatie en bouwjaar.</p>

                <div class="hero-in mt-4 max-w-xl sm:mt-8" style="--d: 0.6">
                    <livewire:search-box />
                </div>

                <form method="post" action="{{ route('plate') }}" class="hero-in mt-4 max-w-xl sm:mt-6" style="--d: 0.75">
                    @csrf
                    <label for="kenteken" class="block text-sm font-semibold text-brand-100">Of zoek op kenteken</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        {{-- ziet eruit als een Nederlands kenteken --}}
                        <div class="plate flex w-full sm:max-w-60 overflow-hidden rounded-md border-2 border-brand-900 bg-accent-400">
                            <span class="flex w-9 items-center justify-center bg-brand-600 text-xs font-semibold text-white" aria-hidden="true">NL</span>
                            <input id="kenteken" name="kenteken" type="text" data-plate-input required maxlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                   placeholder="AB-123-C" aria-describedby="kenteken-hulp{{ session('plate_error') ? ' kenteken-fout' : '' }}" @if (session('plate_error')) aria-invalid="true" @endif
                                   class="w-full bg-transparent px-3 py-2 text-lg font-semibold uppercase tracking-widest text-brand-900 placeholder:text-brand-900/70 focus:outline-none focus:ring-4 focus:ring-inset focus:ring-brand-900">
                        </div>
                        <button type="submit" class="btn max-sm:w-full">Bekijk mijn auto</button>
                    </div>
                    @if (session('plate_error'))
                        <p id="kenteken-fout" role="alert" class="mt-3 rounded-lg bg-white/15 px-4 py-2 text-sm font-medium text-white">{{ session('plate_error') }}</p>
                        @if (session('plate_unknown_car'))
                            @php $car = session('plate_unknown_car'); @endphp
                            <x-plate-price :price="$car['price'] ?? null" />
                            <x-plate-cta :brand="$car['brand']" :model="$car['model']" :year="$car['year']" position="kenteken-onbekend" />
                        @endif
                    @endif
                    <p id="kenteken-hulp" class="sr-only">Je mag de streepjes weglaten.</p>
                    <p class="mt-2 text-sm text-brand-100">We zoeken de auto op bij de RDW en slaan je kenteken niet op.</p>
                </form>
            </div>
        </section>
    </x-slot:hero>

    @if ($popular->isNotEmpty())
        <section aria-labelledby="populair">
            <h2 id="populair" class="reveal text-2xl font-semibold text-brand-900">Populaire modellen</h2>
            <ul class="mt-4 grid gap-3 sm:grid-cols-2 md:grid-cols-3">
                @foreach ($popular as $model)
                    <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                        <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="card-link group flex items-center gap-2">
                            <span>
                                <span class="block text-xs font-medium uppercase tracking-wide text-ink-soft">{{ $model->brand->name }}</span>
                                <span class="block font-semibold">{{ $model->name }}</span>
                            </span>
                            <span class="card-arrow" aria-hidden="true">&rarr;</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="mt-16 border-t border-brand-100 pt-12" aria-labelledby="ranglijsten">
        <h2 id="ranglijsten" class="reveal max-w-xl text-2xl font-semibold text-brand-900">Welke auto heeft de minste gebreken?</h2>
        <p class="reveal mt-2 max-w-2xl text-ink-soft" style="--i: 1">Bekijk de ranglijsten met de beste en de slechtste generaties, per merk, brandstof en bouwjaar. Hoe lager de score, hoe minder gebreken bij de APK.</p>
        <p class="reveal mt-5 flex flex-wrap items-center gap-x-6 gap-y-3" style="--i: 2">
            <a href="{{ route('rankings') }}" class="btn">Naar de ranglijsten</a>
            <a href="{{ route('ranking', ['alles']) }}" class="inline-flex min-h-11 items-center font-medium text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4 hover:text-brand-900">Direct naar de top</a>
        </p>
    </section>
</x-layouts.app>
