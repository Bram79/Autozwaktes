{{-- Cookie banner: only on when ads (or CONSENT_BANNER) are switched on. "Accept" and "Only necessary" look the same, nothing is pre-selected. --}}
@if (config('site.ads.enabled') || config('site.consent_banner'))
    <div id="consent-banner" role="dialog" aria-modal="false" aria-labelledby="consent-title" hidden
         class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-2xl rounded-2xl border-2 border-accent-400 bg-white p-5 shadow-2xl">
        <h2 id="consent-title" class="text-lg font-bold">Cookies en advertenties</h2>
        <p class="mt-1 text-sm text-slate-700">
            We gebruiken altijd de cookies die nodig zijn om de site te laten werken. Met jouw toestemming laden we ook advertenties van een externe partij, die daarbij cookies kan plaatsen.
            Je kunt je keuze altijd wijzigen via "Cookie-instellingen" onderaan de pagina. <a href="{{ route('privacy') }}" class="text-brand-700 underline underline-offset-2">Lees meer</a>.
        </p>
        <div class="mt-4 flex flex-wrap gap-3">
            <button type="button" data-consent="accepted" class="btn">Accepteren</button>
            <button type="button" data-consent="declined" class="btn">Alleen noodzakelijke cookies</button>
        </div>
    </div>
@endif
