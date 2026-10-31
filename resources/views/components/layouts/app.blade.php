<!DOCTYPE html>
<html lang="nl">
<head>
    @php
        // titles and descriptions that fit in the search results (see App\Site\Seo)
        $title = \App\Site\Seo::title((string) $title);
        $description = \App\Site\Seo::description((string) $description);
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    @if (config('site.google_verification'))
        <meta name="google-site-verification" content="{{ config('site.google_verification') }}">
    @endif
    @if (! empty($robots))
        <meta name="robots" content="{{ $robots }}">
    @endif
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @if (config('site.ads.enabled') && config('site.ads.script_url'))
        {{-- only the address: the script itself is loaded by app.js after consent --}}
        <meta name="ads-script" content="{{ config('site.ads.script_url') }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <noscript><style>.reveal, .bar { opacity: 1; transform: none; }</style></noscript>
    {{ $head ?? '' }}
</head>
<body class="bg-white text-brand-900 antialiased">
    <a href="#inhoud" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:font-semibold focus:text-brand-700">Ga naar de inhoud</a>

    <header class="border-b border-brand-100 bg-white/85 backdrop-blur sm:sticky sm:top-0 sm:z-20">
        <div class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-x-4 px-4 py-1">
            <a href="{{ route('home') }}" class="flex min-h-11 items-center gap-2 text-lg font-semibold tracking-tight text-brand-900">
                @if ($logo = \App\Site\Brand::logoUrl())
                    <img src="{{ $logo }}" alt="{{ config('site.name') }}" class="h-7 w-auto">
                @else
                    <span class="inline-block size-2.5 rounded-full bg-accent-400" aria-hidden="true"></span>
                    {{ config('site.name') }}
                @endif
            </a>
            <nav aria-label="Hoofdmenu" class="-mx-3 flex flex-wrap text-sm font-medium sm:mx-0">
                <a href="{{ route('home') }}" class="nav-link">Zoeken</a>
                <a href="{{ route('rankings') }}" class="nav-link">Ranglijsten</a>
                <a href="{{ route('apk') }}" class="nav-link">APK-herinnering</a>
            </nav>
        </div>
    </header>

    {{ $hero ?? '' }}

    <main id="inhoud" tabindex="-1" class="mx-auto max-w-5xl px-4 py-10 focus:outline-none sm:py-14">
        {{ $slot }}
    </main>

    <x-consent-banner />

    <footer class="mt-16 border-t border-white/10 bg-brand-900 text-brand-100">
        <div class="mx-auto max-w-5xl space-y-2 px-4 py-8 text-sm">
            <p class="flex flex-wrap items-center justify-between gap-x-4 font-semibold text-white">{{ config('site.name') }} <span class="flex flex-wrap gap-x-4"><a href="{{ route('privacy') }}" class="inline-flex min-h-11 items-center font-medium text-white underline underline-offset-2 hover:text-brand-100">Privacy</a>@if (config('site.ads.enabled') || config('site.consent_banner'))<button type="button" data-consent-open class="inline-flex min-h-11 items-center font-medium text-white underline underline-offset-2 hover:text-brand-100">Cookie-instellingen</button>@endif</span></p>
            <nav aria-label="Voetmenu" class="flex flex-wrap gap-x-4">
                <a href="{{ route('rankings') }}" class="inline-flex min-h-11 items-center text-brand-100 underline underline-offset-2 hover:text-white">Ranglijsten</a>
                <a href="{{ route('apk') }}" class="inline-flex min-h-11 items-center text-brand-100 underline underline-offset-2 hover:text-white">APK-herinnering</a>
            </nav>
            <p>Bron: RDW open data (CC-0). {{ config('site.name') }} is een onafhankelijke website en is niet gelieerd aan de overheid of de RDW.</p>
            <p>De cijfers zijn een indicatie: ze zijn gebaseerd op geregistreerde voertuigen en geconstateerde APK-gebreken en zeggen niets over een individuele auto. Laat een auto altijd zelf keuren voordat je koopt.</p>
        </div>
    </footer>
</body>
</html>
