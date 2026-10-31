@php
    $name = \App\Site\Seo::modelName($model);
    // with one generation this page and the page of that generation are the same search: the generation page is the one for Google
    $canonical = $generations->count() === 1 ? route('generation', [$model->brand, $model->slug, $generations->first()->slug]) : route('model', [$model->brand, $model->slug]);
    $from = $generations->min('year_from');
    $to = $generations->contains(fn ($g) => $g->year_to === null) ? 'heden' : $generations->max('year_to');
    $description = $generations->isEmpty()
        ? 'Overzicht van alle generaties van de '.$name.' met de meest voorkomende APK-gebreken.'
        : 'De '.$name.': '.($generations->count() === 1 ? '1 generatie' : $generations->count().' generaties').' ('.$from.'–'.$to.') met de meest voorkomende APK-gebreken, percentages en een koopchecklist per generatie en bouwjaar.';
@endphp
<x-layouts.app
    :title="$name.': generaties, zwakke punten en APK-problemen'"
    :description="$description"
    :canonical="$canonical"
    :robots="$generations->isEmpty() ? 'noindex,follow' : null">

    <x-breadcrumbs :items="[['Home', route('home')], [$model->brand->name, route('brand', $model->brand)], [$model->name, route('model', [$model->brand, $model->slug])]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $name }}: generaties</h1>
    @unless ($generations->isEmpty())
        <p class="mt-3 max-w-2xl text-ink-soft">{{ $generations->count() === 1 ? 'Er is 1 generatie' : 'Er zijn '.$generations->count().' generaties' }} van de {{ $name }} met cijfers. Kies de generatie die je overweegt voor de top-gebreken, een koopchecklist en het verschil per bouwjaar.</p>
    @endunless

    {{-- Someone who looked up a plate and ended here (no numbers for that year) still sees the vehicle report first --}}
    @if (\App\Site\PlateVisit::forModel($model->id))
        <x-plate-price />
        <x-plate-cta :brand="$model->brand->name" :model="$model->name" :year="\App\Site\PlateVisit::year() ?? ''" position="kenteken-resultaat-model" />
    @endif

    @if ($generations->isEmpty())
        <div class="mt-6 panel text-ink-soft">
            <p class="font-semibold text-brand-900">Nog geen cijfers</p>
            <p class="mt-1 text-sm">Voor dit model zijn nog geen cijfers beschikbaar. Kijk bij <a href="{{ route('brand', $model->brand) }}" class="font-medium text-brand-700 underline underline-offset-2">alle {{ $model->brand->name }}-modellen</a>.</p>
        </div>
    @else
        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($generations as $generation)
                <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                    <a href="{{ route('generation', [$model->brand, $model->slug, $generation->slug]) }}" class="card-link group flex items-center gap-3">
                        <span>
                            <span class="block font-semibold text-brand-700">{{ \App\Site\Seo::generationName($generation) }}</span>
                            <span class="mt-0.5 inline-block rounded-full bg-brand-50 px-2.5 py-0.5 text-sm text-brand-900">{{ \App\Site\Seo::yearsLabel($generation) }}</span>
                        </span>
                        <span class="card-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    @unless ($generations->isEmpty())
        <div class="mt-10 grid gap-4 md:grid-cols-2">
            @unless (\App\Site\PlateVisit::forModel($model->id))
                <x-affiliate-block type="vehicle_report" :brand="$model->brand->name" :model="$model->name" position="modelpagina" :prominent="true" />
            @endunless
            <x-affiliate-block type="insurance" :brand="$model->brand->name" :model="$model->name" position="modelpagina" />
        </div>
    @endunless

    @if ($suggestions)
        <section class="mt-10 panel" aria-labelledby="vergelijken">
            <h2 id="vergelijken" class="text-xl font-semibold">Vergelijk de {{ $name }} met</h2>
            <p class="mt-1 text-sm text-ink-soft">Zie welke van de twee bij de APK de minste gebreken heeft.</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($suggestions as $suggestion)
                    <li><a href="{{ route('comparison', $suggestion['slug']) }}" class="chip">{{ $suggestion['name'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($similar->isNotEmpty())
        <section class="mt-10" aria-labelledby="meer">
            <h2 id="meer" class="text-xl font-semibold">Andere {{ $model->brand->name }}-modellen</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($similar as $other)
                    <li><a href="{{ route('model', [$model->brand, $other->slug]) }}" class="chip">{{ $other->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
