{{-- Cookie banner: only on when ads (or CONSENT_BANNER) are switched on. "Accept" and "Only necessary" look the same, nothing is pre-selected. --}}
@if (config('site.ads.enabled') || config('site.consent_banner'))
    <div id="consent-banner" role="dialog" aria-modal="false" aria-labelledby="consent-title" hidden
         class="fixed inset-x-3 bottom-3 z-50 mx-auto max-w-2xl max-h-[90svh] overflow-y-auto rounded-lg border border-brand-600 bg-white p-5">
        <h2 id="consent-title" class="text-lg font-semibold">Cookies en advertenties</h2>
        <p class="mt-1 text-sm text-ink-soft">
            We gebruiken altijd de cookies die nodig zijn om de site te laten werken. Met jouw toestemming laden we ook advertenties van een externe partij, die daarbij cookies kan plaatsen.
            Je kunt je keuze altijd wijzigen via "Cookie-instellingen" onderaan de pagina. <a href="{{ route('privacy') }}" class="text-brand-700 underline underline-offset-2 hover:text-brand-900">Lees meer</a>.
        </p>
        <div class="mt-4 grid gap-3 sm:flex sm:flex-wrap">
            <button type="button" data-consent="accepted" class="btn">Accepteren</button>
            <button type="button" data-consent="declined" class="btn">Alleen noodzakelijke cookies</button>
        </div>
    </div>
@endif
