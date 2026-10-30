@props(['type', 'brand' => '', 'model' => '', 'year' => ''])

@php
    $config = \App\Models\Setting::affiliate($type);
    $replace = fn ($text) => str_replace(['{merk}', '{model}', '{jaar}'], [$brand, $model, $year], $text);
@endphp

@if ($config && ($config['enabled'] ?? false) && ! empty($config['url']))
    <aside class="rounded-2xl border border-accent-400 bg-brand-50 p-5 shadow-sm" aria-label="Advertentie">
        <p class="text-xs font-bold uppercase tracking-wide text-brand-700">Affiliate-link</p>
        <h3 class="mt-1 font-semibold">{{ $replace($config['title']) }}</h3>
        <p class="mt-1 text-sm text-slate-700">{{ $replace($config['text']) }}</p>
        <a href="{{ $replace($config['url']) }}" rel="sponsored nofollow noopener" target="_blank"
           class="btn mt-3 text-sm">
            {{ $config['button'] }}
        </a>
        <p class="mt-2 text-xs text-slate-600">Dit is een affiliate-link: als je hierop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet.</p>
    </aside>
@endif
