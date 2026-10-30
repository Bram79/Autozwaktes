<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="nl_NL">
    <meta property="og:url" content="{{ $canonical ?? url()->current() }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="bg-white text-slate-900 antialiased">
    <a href="#inhoud" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-10 focus:rounded focus:bg-white focus:px-3 focus:py-2 focus:text-blue-800">Ga naar de inhoud</a>

    <header class="border-b border-slate-200 bg-slate-50">
        <div class="mx-auto flex max-w-5xl items-center justify-between px-4 py-3">
            <a href="{{ route('home') }}" class="text-lg font-bold text-blue-900">Autozwaktes<span class="text-slate-600">.nl</span></a>
            <nav aria-label="Hoofdmenu" class="text-sm">
                <a href="{{ route('home') }}" class="rounded px-2 py-1 text-blue-800 underline-offset-2 hover:underline">Zoeken</a>
            </nav>
        </div>
    </header>

    <main id="inhoud" class="mx-auto max-w-5xl px-4 py-6">
        {{ $slot }}
    </main>

    <footer class="mt-12 border-t border-slate-200 bg-slate-50">
        <div class="mx-auto max-w-5xl space-y-2 px-4 py-6 text-sm text-slate-700">
            <p>Bron: RDW open data (CC-0). Autozwaktes.nl is een onafhankelijke website en is niet gelieerd aan de overheid of de RDW.</p>
            <p>De cijfers zijn een indicatie: ze zijn gebaseerd op geregistreerde voertuigen en geconstateerde APK-gebreken sinds 2022 en zeggen niets over een individuele auto. Laat een auto altijd zelf keuren voordat je koopt.</p>
        </div>
    </footer>
</body>
</html>
