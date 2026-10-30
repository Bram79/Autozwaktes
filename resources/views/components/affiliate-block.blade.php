@props(['type', 'brand' => '', 'model' => '', 'year' => ''])

@php
    $config = config("site.affiliates.{$type}");
    $replace = fn ($text) => str_replace(['{merk}', '{model}', '{jaar}'], [$brand, $model, $year], $text);
@endphp

@if ($config && ($config['enabled'] ?? false) && ! empty($config['url']))
    <aside class="rounded-lg border border-slate-300 bg-slate-50 p-4" aria-label="Advertentie">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-600">Affiliate-link</p>
        <h3 class="mt-1 font-semibold">{{ $replace($config['title']) }}</h3>
        <p class="mt-1 text-sm text-slate-700">{{ $replace($config['text']) }}</p>
        <a href="{{ $replace($config['url']) }}" rel="sponsored nofollow noopener" target="_blank"
           class="mt-3 inline-block rounded bg-blue-800 px-4 py-2 text-sm font-medium text-white hover:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-800 focus:ring-offset-2">
            {{ $config['button'] }}
        </a>
        <p class="mt-2 text-xs text-slate-600">Dit is een affiliate-link: als je hierop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet.</p>
    </aside>
@endif
