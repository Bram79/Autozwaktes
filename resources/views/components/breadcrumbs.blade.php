@props(['items'])

@php
    $list = collect($items)->values()->map(fn ($item, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $item[0],
        'item' => $item[1],
    ]);
@endphp

<nav aria-label="Kruimelpad" class="mb-2 text-sm text-ink-soft">
    <ol class="flex flex-wrap items-center gap-x-2">
        @foreach ($items as $i => $item)
            <li class="flex min-h-11 items-center gap-2">
                @if ($i > 0)<span aria-hidden="true">/</span>@endif
                @if ($loop->last)
                    <span aria-current="page" class="font-medium text-brand-900">{{ $item[0] }}</span>
                @else
                    <a href="{{ $item[1] }}" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">{{ $item[0] }}</a>
                @endif
            </li>
        @endforeach
    </ol>
</nav>

{{-- built in PHP: in Blade an "@context" key would be read as a directive and break the JSON --}}
@php
    $schema = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
@endphp
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
