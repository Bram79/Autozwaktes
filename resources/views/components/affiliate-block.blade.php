@props(['type', 'brand' => '', 'model' => '', 'year' => '', 'position' => 'zijbalk', 'prominent' => false])

@php
    $config = \App\Models\Setting::affiliate($type);
    $replace = fn ($text) => str_replace(['{merk}', '{model}', '{jaar}'], [$brand, $model, $year], $text);
@endphp

{{-- Only shown when the partner is switched on and has a link. The visitor goes through /go so that the click is counted. --}}
@if ($config && \App\Site\AffiliateLink::target($type, $brand, $model, (string) $year))
    <aside class="{{ $prominent ? 'ad-card-prominent' : 'ad-card' }}" aria-label="Advertentie">
        <p class="text-xs font-semibold uppercase tracking-wide {{ $prominent ? 'text-brand-100' : 'text-brand-700' }}">Advertentie · Partnerlink</p>
        <p class="mt-1 {{ $prominent ? 'text-xl font-semibold text-white' : 'font-semibold text-brand-900' }}">{{ $replace($config['title']) }}</p>
        <p class="mt-1 text-sm {{ $prominent ? 'text-brand-100' : 'text-ink-soft' }}">{{ $replace($config['text']) }}</p>
        <a href="{{ \App\Site\AffiliateLink::url($type, $position, $brand, $model, $year) }}" rel="sponsored nofollow noopener" target="_blank" class="btn mt-3 text-sm">
            {{ $config['button'] }}<span class="sr-only"> (opent in een nieuw tabblad)</span>
        </a>
        <p class="mt-2 text-xs {{ $prominent ? 'text-brand-100' : 'text-ink-soft' }}">Dit is een affiliate-link: als je hierop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet.</p>
    </aside>
@endif
