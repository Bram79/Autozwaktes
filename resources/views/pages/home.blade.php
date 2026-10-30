<x-layouts.app
    title="Auto Zwaktes: zwakke punten en APK-problemen per automodel"
    description="Zoek je auto en zie welke APK-gebreken het vaakst voorkomen per merk, model, generatie en bouwjaar. Handig bij het kopen van een occasion.">

    <x-slot:hero>
        <section data-spotlight class="relative isolate overflow-hidden bg-brand-700 bg-gradient-to-br from-brand-900 via-brand-700 to-brand-600 text-white">
            {{-- Rustige achtergrond: fijn raster, langzaam verschuivende kleurvlakken en een lichtvlek die de muis volgt. Puur decoratief. --}}
            <div class="hero-grid" aria-hidden="true"></div>
            <div class="aurora aurora-a" aria-hidden="true"></div>
            <div class="aurora aurora-b" aria-hidden="true"></div>
            <div class="aurora aurora-c" aria-hidden="true"></div>
            <div class="hero-spotlight" data-spotlight-glow aria-hidden="true"></div>

            <div class="relative mx-auto max-w-5xl px-4 py-8 sm:py-24">
                {{-- Lijntekening van een auto die zichzelf tekent; alleen op brede schermen --}}
                <svg class="hero-car pointer-events-none absolute bottom-20 right-4 hidden w-80 text-white/60 lg:block xl:w-96" viewBox="0 0 306 110" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
                    <path pathLength="1" style="--n: 0" d="M12 84V68Q12 62 20 60L58 52L90 28Q94 24 102 24H186Q194 24 200 29L232 54L274 60Q292 63 294 74V84"/>
                    <path pathLength="1" style="--n: 1" d="M12 84H48A24 24 0 0 1 96 84H210A24 24 0 0 1 258 84H294"/>
                    <path pathLength="1" style="--n: 2" d="M92 52L108 32H152V52Z"/>
                    <path pathLength="1" style="--n: 2" d="M162 52V32H186Q190 32 194 36L214 52Z"/>
                    <circle pathLength="1" style="--n: 3" class="text-accent-300" cx="72" cy="84" r="16" stroke="currentColor"/>
                    <circle pathLength="1" style="--n: 4" class="text-accent-300" cx="234" cy="84" r="16" stroke="currentColor"/>
                </svg>

                <h1 class="max-w-3xl text-3xl font-extrabold leading-[1.1] tracking-tight text-accent-300 sm:text-6xl">
                    <span class="hero-mask"><span class="hero-line">Zwakke punten per automodel</span></span>
                </h1>
                <p class="hero-in mt-5 hidden max-w-2xl text-lg text-brand-100 sm:block" style="--d: 0.45">Weet waar jouw volgende auto mee zit. Zoek een merk of model en zie welke APK-gebreken het vaakst voorkomen, per generatie en bouwjaar.</p>

                <div class="hero-in mt-4 max-w-xl sm:mt-8" style="--d: 0.6">
                    <livewire:search-box />
                </div>

                <form method="post" action="{{ route('plate') }}" class="hero-in mt-4 max-w-xl sm:mt-6" style="--d: 0.75">
                    @csrf
                    <label for="kenteken" class="block text-sm font-semibold text-brand-100">Of zoek op kenteken</label>
                    <div class="mt-2 flex flex-wrap gap-2">
                        {{-- ziet eruit als een Nederlands kenteken --}}
                        <div class="plate flex w-full sm:max-w-60 overflow-hidden rounded-md border-2 border-brand-900 bg-accent-400 shadow-lg">
                            <span class="flex w-9 items-center justify-center bg-brand-600 text-xs font-bold text-white" aria-hidden="true">NL</span>
                            <input id="kenteken" name="kenteken" type="text" data-plate-input required maxlength="10" autocomplete="off" autocapitalize="characters" spellcheck="false"
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
                        <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="card-link group flex items-center gap-2" data-tilt>
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

    <section class="relative isolate mt-12 overflow-hidden rounded-2xl bg-brand-900 p-6 ring-1 ring-inset ring-white/10 text-white sm:p-8" aria-labelledby="ranglijsten">
        <div class="hero-grid" aria-hidden="true"></div>
        <div class="glow -right-16 -top-20 size-72 sm:-right-12 sm:size-96" aria-hidden="true"></div>
        <h2 id="ranglijsten" class="reveal relative max-w-xl text-2xl font-extrabold text-accent-300">Welke auto heeft de minste gebreken?</h2>
        <p class="reveal relative mt-2 max-w-2xl text-brand-100" style="--i: 1">Bekijk de ranglijsten met de beste en de slechtste generaties, per merk, brandstof en bouwjaar. Hoe lager de score, hoe minder gebreken bij de APK.</p>
        <p class="reveal relative mt-5 flex flex-wrap gap-3" style="--i: 2">
            <a href="{{ route('rankings') }}" class="btn">Naar de ranglijsten</a>
            <a href="{{ route('ranking', ['alles']) }}" class="btn-outline">Direct naar de top</a>
        </p>
    </section>
</x-layouts.app>
