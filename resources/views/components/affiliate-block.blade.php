@props(['type', 'brand' => '', 'model' => '', 'year' => '', 'position' => 'zijbalk', 'prominent' => false])

@php
    $config = \App\Models\Setting::affiliate($type);
    $replace = fn ($text) => str_replace(['{merk}', '{model}', '{jaar}'], [$brand, $model, $year], $text);
@endphp

{{-- Only shown when the partner is switched on and has a link. The visitor goes through /go so that the click is counted. --}}
@if ($config && \App\Site\AffiliateLink::target($type, $brand, $model, (string) $year))
    <aside class="{{ $prominent ? 'rounded-2xl border-2 border-accent-400 bg-brand-900 p-6 text-white shadow-lg' : 'rounded-2xl border border-accent-400 bg-brand-50 p-5 shadow-sm' }}" aria-label="Advertentie">
        <p class="text-xs font-bold uppercase tracking-wide {{ $prominent ? 'text-accent-300' : 'text-brand-700' }}">Affiliate-link</p>
        <h3 class="mt-1 {{ $prominent ? 'text-xl font-extrabold text-white' : 'font-semibold' }}">{{ $replace($config['title']) }}</h3>
        <p class="mt-1 text-sm {{ $prominent ? 'text-brand-100' : 'text-slate-700' }}">{{ $replace($config['text']) }}</p>
        <a href="{{ \App\Site\AffiliateLink::url($type, $position, $brand, $model, $year) }}" rel="sponsored nofollow noopener" target="_blank" class="btn mt-3 text-sm">
            {{ $config['button'] }}
        </a>
        <p class="mt-2 text-xs {{ $prominent ? 'text-brand-100' : 'text-slate-600' }}">Dit is een affiliate-link: als je hierop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet.</p>
    </aside>
@endif
