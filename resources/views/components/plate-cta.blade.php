@props(['brand', 'model', 'year' => '', 'position' => 'kenteken-resultaat'])

{{-- The highest buying intention on the site: someone who just looked up the plate of a car. Shown right below the title. --}}
@if (\App\Site\AffiliateLink::target('vehicle_report', $brand, $model, (string) $year))
    @php $config = \App\Models\Setting::affiliate('vehicle_report'); @endphp
    <aside class="mt-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-2 border-accent-400 bg-brand-900 p-5 text-white shadow-lg" aria-label="Advertentie">
        <div class="max-w-xl">
            <p class="text-xs font-bold uppercase tracking-wide text-accent-300">Advertentie · Partnerlink</p>
            <p class="mt-1 text-xs text-brand-100">Wij krijgen een vergoeding als je iets afneemt. Voor jou verandert de prijs niet.</p>
            <h2 class="mt-2 text-xl font-extrabold text-white">Koop je een {{ $brand }} {{ $model }}{{ $year ? ' uit '.$year : '' }}? Check eerst de historie</h2>
            <p class="mt-1 text-sm text-brand-100">{{ str_replace(['{merk}', '{model}', '{jaar}'], [$brand, $model, $year], $config['text']) }}</p>
        </div>
        <a href="{{ \App\Site\AffiliateLink::url('vehicle_report', $position, $brand, $model, $year) }}" rel="sponsored nofollow noopener" target="_blank" class="btn">{{ $config['button'] }}</a>
    </aside>
@endif
