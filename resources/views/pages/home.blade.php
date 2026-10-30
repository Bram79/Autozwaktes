<x-layouts.app
    title="Autozwaktes.nl: zwakke punten en APK-problemen per automodel"
    description="Zoek je auto en zie welke APK-gebreken het vaakst voorkomen per merk, model, generatie en bouwjaar. Handig bij het kopen van een occasion.">

    <h1 class="text-3xl font-bold">Zwakke punten per automodel</h1>
    <p class="mt-2 max-w-2xl text-slate-700">Zoek een merk of model en zie welke APK-gebreken het vaakst voorkomen.</p>

    <div class="mt-6 max-w-xl">
        <livewire:search-box />
    </div>

    @if ($popular->isNotEmpty())
        <section class="mt-10" aria-labelledby="populair">
            <h2 id="populair" class="text-xl font-bold">Populaire modellen</h2>
            <ul class="mt-3 grid gap-2 sm:grid-cols-2 md:grid-cols-3">
                @foreach ($popular as $model)
                    <li>
                        <a href="{{ route('model', [$model->brand->slug, $model->slug]) }}" class="block rounded border border-slate-200 px-3 py-2 text-blue-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-800">
                            {{ $model->brand->name }} {{ $model->name }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
