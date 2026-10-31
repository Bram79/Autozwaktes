<x-layouts.app
    :title="'Ranglijsten: welke auto\'s hebben de minste APK-gebreken? | '.config('site.name')"
    description="Ranglijsten van auto's met de minste en de meeste APK-gebreken, per merk, brandstof en bouwjaar. Gebaseerd op RDW-cijfers."
    :canonical="route('rankings')">

    <x-breadcrumbs :items="[['Home', route('home')], ['Ranglijsten', route('rankings')]]" />

    <h1 class="text-3xl sm:text-4xl">Ranglijsten: de minste en meeste APK-gebreken</h1>
    <p class="mt-3 max-w-2xl text-ink-soft">Welke generaties hebben bij de APK het minst vaak gebreken? Elke lijst rangschikt auto's op het aantal geconstateerde gebreken per 100 auto's. Hoe lager, hoe beter.</p>

    <p class="mt-6"><a href="{{ route('ranking', ['alles']) }}" class="btn">Alle auto's bij elkaar</a></p>

    @include('partials.ranking-links', ['brands' => $brands, 'fuels' => $fuels, 'years' => $years])
</x-layouts.app>
