@props(['items'])

@php
    $list = collect($items)->values()->map(fn ($item, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $item[0],
        'item' => $item[1],
    ]);
@endphp

<nav aria-label="Kruimelpad" class="mb-4 text-sm text-slate-700">
    <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
        @foreach ($items as $i => $item)
            <li class="flex items-center gap-2">
                @if ($i > 0)<span aria-hidden="true">/</span>@endif
                @if ($loop->last)
                    <span aria-current="page" class="font-medium text-slate-900">{{ $item[0] }}</span>
                @else
                    <a href="{{ $item[1] }}" class="text-blue-800 underline underline-offset-2">{{ $item[0] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
