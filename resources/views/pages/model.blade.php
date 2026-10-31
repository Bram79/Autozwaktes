@php $name = \App\Site\Seo::modelName($model); @endphp
<x-layouts.app
    :title="$name.': generaties, zwakke punten en APK-problemen'"
    :description="'Overzicht van alle generaties van de '.$name.' met de meest voorkomende APK-gebreken.'"
    :robots="$generations->isEmpty() ? 'noindex,follow' : null">

    <x-breadcrumbs :items="[['Home', route('home')], [$model->brand->name, route('brand', $model->brand)], [$model->name, route('model', [$model->brand, $model->slug])]]" />

    <h1 class="text-3xl sm:text-4xl">{{ $name }}: generaties</h1>
    @unless ($generations->isEmpty())
        <p class="mt-3 max-w-2xl text-slate-700">Er zijn {{ $generations->count() }} {{ $generations->count() === 1 ? 'generatie' : 'generaties' }} van de {{ $name }} met cijfers. Kies de generatie die je overweegt voor de top-gebreken, een koopchecklist en het verschil per bouwjaar.</p>
    @endunless

    {{-- Someone who looked up a plate and ended here (no numbers for that year) still sees the vehicle report first --}}
    @if (\App\Site\PlateVisit::active())
        <x-plate-cta :brand="$model->brand->name" :model="$model->name" position="kenteken-resultaat-model" />
    @endif

    @if ($generations->isEmpty())
        <div class="mt-6 rounded-2xl border border-brand-100 bg-brand-50 p-5 text-slate-700">
            <p class="font-semibold text-brand-900">Nog geen cijfers</p>
            <p class="mt-1 text-sm">Voor dit model zijn nog geen cijfers beschikbaar. Kijk bij <a href="{{ route('brand', $model->brand) }}" class="font-medium text-brand-700 underline underline-offset-2">alle {{ $model->brand->name }}-modellen</a>.</p>
        </div>
    @else
        <ul class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($generations as $generation)
                <li class="reveal" style="--i: {{ min($loop->index, 8) }}">
                    <a href="{{ route('generation', [$model->brand, $model->slug, $generation->slug]) }}" class="card-link group flex items-center gap-3">
                        <span>
                            <span class="block font-semibold text-brand-700">{{ $name }} {{ $generation->code }}</span>
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
            @unless (\App\Site\PlateVisit::active())
                <x-affiliate-block type="vehicle_report" :brand="$model->brand->name" :model="$model->name" position="modelpagina" :prominent="true" />
            @endunless
            <x-affiliate-block type="insurance" :brand="$model->brand->name" :model="$model->name" position="modelpagina" />
        </div>
    @endunless

    @php $suggestions = app(\App\Site\ComparisonService::class)->suggestions($model); @endphp
    @if ($suggestions)
        <section class="mt-10 rounded-2xl border border-brand-100 bg-brand-50 p-5" aria-labelledby="vergelijken">
            <h2 id="vergelijken" class="text-xl font-bold">Vergelijk de {{ $name }} met</h2>
            <p class="mt-1 text-sm text-slate-700">Zie welke van de twee bij de APK de minste gebreken heeft.</p>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($suggestions as $suggestion)
                    <li><a href="{{ route('comparison', $suggestion['slug']) }}" class="chip">{{ $suggestion['name'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($similar->isNotEmpty())
        <section class="mt-10" aria-labelledby="meer">
            <h2 id="meer" class="text-xl font-bold">Andere {{ $model->brand->name }}-modellen</h2>
            <ul class="mt-3 flex flex-wrap gap-2">
                @foreach ($similar as $other)
                    <li><a href="{{ route('model', [$model->brand, $other->slug]) }}" class="chip">{{ $other->name }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif
</x-layouts.app>
