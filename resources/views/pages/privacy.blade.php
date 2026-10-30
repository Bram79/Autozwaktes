<x-layouts.app
    title="Privacyverklaring | Auto Zwaktes"
    description="Welke gegevens Auto Zwaktes verwerkt, waarom, en welke rechten je hebt.">

    <x-breadcrumbs :items="[['Home', route('home')], ['Privacy', route('privacy')]]" />

    <article class="max-w-3xl space-y-8">
        <header>
            <h1 class="text-3xl sm:text-4xl">Privacyverklaring</h1>
            <p class="mt-2 text-slate-600">Laatst bijgewerkt: 31 oktober 2026</p>
        </header>

        <section class="space-y-2" aria-labelledby="kort">
            <h2 id="kort" class="text-xl font-bold">In het kort</h2>
            <p>Auto Zwaktes vraagt je geen account, naam of e-mailadres. We gebruiken geen advertentie- of volgcookies en geen statistiekdienst die je volgt. Hieronder lees je precies wat er wel gebeurt.</p>
        </section>

        <section class="space-y-2" aria-labelledby="kenteken">
            <h2 id="kenteken" class="text-xl font-bold">Zoeken op kenteken</h2>
            <p>Als je een kenteken invult, sturen we dat naar de open data van de RDW om te zien om welke auto het gaat. Daarna sturen we je door naar de pagina met de zwakke punten van dat model.</p>
            <p>We slaan je kenteken niet op. Om de site snel te houden bewaren we de openbare autogegevens (merk, model, bouwjaar) maximaal 24 uur tijdelijk, onder een versleutelde sleutel en niet gekoppeld aan jou.</p>
        </section>

        <section class="space-y-2" aria-labelledby="apk">
            <h2 id="apk" class="text-xl font-bold">APK-herinnering</h2>
            <p>Als je een APK-herinnering aanvraagt, bewaren we je e-mailadres, je kenteken en de vervaldatum van de APK (die halen we uit de open data van de RDW). We gebruiken dit alleen om je één mail te sturen ongeveer 30 dagen voor de APK verloopt.</p>
            <p>Dit werkt met dubbele bevestiging: we sturen eerst een mail met een bevestigingslink. Bevestig je niet binnen een week, dan verwijderen we je gegevens. Onderaan elke mail staat een link waarmee je je meteen uitschrijft; dan verwijderen we je e-mailadres en kenteken direct. We delen deze gegevens met niemand. De rechtsgrond is jouw toestemming.</p>
        </section>

        <section class="space-y-2" aria-labelledby="cookies">
            <h2 id="cookies" class="text-xl font-bold">Cookies</h2>
            <p>De site gebruikt functionele cookies: een sessiecookie en een beveiligingscookie die nodig zijn om formulieren en de zoekfunctie te laten werken, en een cookie die onthoudt wat je in de cookiebanner koos. Daarvoor is geen toestemming nodig.</p>
            @if (config('site.ads.enabled') || config('site.consent_banner'))
                <p>Als je dat goedvindt, laden we ook advertenties van een externe partij. Die partij kan zelf cookies plaatsen. Dat gebeurt pas nadat je op "Accepteren" klikt. Je kunt je keuze altijd wijzigen met "Cookie-instellingen" onderaan de pagina.</p>
            @else
                <p>We plaatsen geen tracking- of marketingcookies.</p>
            @endif
            <p>We tellen wel hoe vaak er op een affiliate-link wordt geklikt, per partner en per pagina. Daarbij bewaren we geen IP-adres en geen andere gegevens waarmee je te herkennen bent.</p>
        </section>

        <section class="space-y-2" aria-labelledby="logs">
            <h2 id="logs" class="text-xl font-bold">Technische gegevens</h2>
            <p>Onze server en hostingpartij leggen tijdelijk technische gegevens vast, zoals je IP-adres en welke pagina je opvroeg. Dat is nodig voor de beveiliging en om storingen op te lossen. Voor het zoeken op kenteken gebruiken we je IP-adres één minuut om te veel zoekopdrachten achter elkaar te voorkomen.</p>
        </section>

        <section class="space-y-2" aria-labelledby="affiliate">
            <h2 id="affiliate" class="text-xl font-bold">Affiliate-links</h2>
            <p>Op sommige pagina's staan links naar andere websites, bijvoorbeeld voor een kentekenrapport of een verzekering. Als je daarop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet. Zodra je op zo'n link klikt, gelden de privacyregels en cookies van die website.</p>
        </section>

        <section class="space-y-2" aria-labelledby="bron">
            <h2 id="bron" class="text-xl font-bold">Waar de cijfers vandaan komen</h2>
            <p>De cijfers komen uit de open data van de RDW (CC-0) en gaan over voertuigen, niet over personen. Auto Zwaktes is een onafhankelijke website en niet gelieerd aan de overheid of de RDW.</p>
        </section>

        <section class="space-y-2" aria-labelledby="rechten">
            <h2 id="rechten" class="text-xl font-bold">Jouw rechten en contact</h2>
            <p>Je mag vragen welke gegevens we van je hebben, ze laten corrigeren of laten verwijderen. Omdat we vrijwel niets van bezoekers bewaren, zal dat meestal weinig opleveren. Je kunt ook een klacht indienen bij de <a href="https://autoriteitpersoonsgegevens.nl" class="text-brand-700 underline underline-offset-2" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
            @if (config('site.contact_email'))
                <p>Vragen over privacy? Mail naar <a href="mailto:{{ config('site.contact_email') }}" class="text-brand-700 underline underline-offset-2">{{ config('site.contact_email') }}</a>.</p>
            @endif
        </section>
    </article>
</x-layouts.app>
