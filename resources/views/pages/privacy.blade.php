@php $ads = config('site.ads.enabled') || config('site.consent_banner'); @endphp
<x-layouts.app
    :title="'Privacyverklaring | '.config('site.name')"
    :description="'Welke gegevens '.config('site.name').' verwerkt, waarom, hoe lang we ze bewaren en welke rechten je hebt.'">

    <x-breadcrumbs :items="[['Home', route('home')], ['Privacy', route('privacy')]]" />

    <article class="max-w-3xl space-y-8 leading-relaxed">
        <header>
            <h1 class="text-3xl sm:text-4xl">Privacyverklaring</h1>
            <p class="mt-2 text-ink-soft">Laatst bijgewerkt: 31 oktober 2026</p>
        </header>

        <section class="space-y-2" aria-labelledby="kort">
            <h2 id="kort" class="text-xl font-semibold">In het kort</h2>
            <p>{{ config('site.name') }} vraagt je geen account of naam. Je e-mailadres en kenteken vragen we alleen als je een APK-herinnering aanvraagt. Het kenteken dat je invult om een auto op te zoeken, bewaren we niet.</p>
            @if ($ads)
                <p>Advertentie- of volgcookies plaatsen we alleen met jouw toestemming, en we gebruiken geen statistiekdienst die je volgt.</p>
            @else
                <p>We gebruiken geen advertentie- of volgcookies en geen statistiekdienst die je volgt.</p>
            @endif
            <p>Hieronder lees je precies wat er wel gebeurt.</p>
        </section>

        <section class="space-y-2" aria-labelledby="verantwoordelijke">
            <h2 id="verantwoordelijke" class="text-xl font-semibold">Wie is verantwoordelijk</h2>
            @if (config('site.operator_name'))
                <p>{{ config('site.name') }} wordt beheerd door {{ config('site.operator_name') }}@if (config('site.operator_kvk')) (KvK-nummer {{ config('site.operator_kvk') }})@endif. Die is verantwoordelijk voor de gegevens die op deze pagina staan.</p>
            @endif
            @if (config('site.contact_email'))
                <p>Vragen over privacy of je gegevens? Mail naar <a href="mailto:{{ config('site.contact_email') }}" class="text-brand-700 underline underline-offset-2">{{ config('site.contact_email') }}</a>.</p>
            @endif
        </section>

        <section class="space-y-2" aria-labelledby="kenteken">
            <h2 id="kenteken" class="text-xl font-semibold">Zoeken op kenteken</h2>
            <p>Als je een kenteken invult, sturen we dat naar de open data van de RDW om te zien om welke auto het gaat. Daarna sturen we je door naar de pagina met de zwakke punten van dat model.</p>
            <p>We bewaren je kenteken niet en schrijven het ook niet in onze logbestanden. Om de site snel te houden onthouden we de openbare modelgegevens (merk, model, bouwjaar en catalogusprijs) maximaal 24 uur, zonder kenteken, onder een sleutel die niemand zonder onze geheime sleutel kan terugrekenen.</p>
            <p>Verder onthouden we 30 minuten in je sessie dat je via het zoeken op kenteken kwam, het model en bouwjaar van die auto en wat de catalogusprijs (nieuwprijs) ervan is (zonder het kenteken zelf), zodat we je die prijs op de volgende pagina's kunnen blijven tonen en je een passende partnerlink kunnen tonen.</p>
        </section>

        <section class="space-y-2" aria-labelledby="apk">
            <h2 id="apk" class="text-xl font-semibold">APK-herinnering</h2>
            <p>Als je een APK-herinnering aanvraagt, bewaren we je e-mailadres, je kenteken en de vervaldatum van de APK (die halen we uit de open data van de RDW). We gebruiken dit alleen om je elk jaar één mail te sturen ongeveer 30 dagen voordat de APK verloopt, zolang je herinnering loopt. De rechtsgrond is jouw toestemming. Staat er in de herinneringsmail een partnerlink, dan staat die duidelijk aangeduid als "Advertentie · Partnerlink"; je e-mailadres of kenteken gaat daarbij niet naar de partner.</p>
            <p>Dit werkt met dubbele bevestiging: we sturen eerst een mail met een bevestigingslink. Bevestig je niet binnen een week, dan verwijderen we je gegevens. Onderaan elke mail staat een link waarmee je je meteen uitschrijft; dan verwijderen we je e-mailadres en kenteken direct uit onze database. Is de APK meer dan een jaar verlopen zonder dat er een nieuwe datum bij de RDW staat (de auto is dan waarschijnlijk verkocht of gesloopt), dan verwijderen we je gegevens ook automatisch.</p>
        </section>

        <section class="space-y-2" aria-labelledby="cookies">
            <h2 id="cookies" class="text-xl font-semibold">Cookies</h2>
            <p>De site gebruikt functionele cookies: een sessiecookie en een beveiligingscookie die nodig zijn om formulieren en de zoekfunctie te laten werken, en een cookie die onthoudt wat je in de cookiebanner koos. Daarvoor is geen toestemming nodig.</p>
            <table class="w-full text-left text-sm">
                <caption class="sr-only">De cookies van deze site</caption>
                <thead>
                    <tr class="border-b border-brand-600">
                        <th scope="col" class="py-2 pr-4">Cookie</th>
                        <th scope="col" class="py-2 pr-4">Waarvoor</th>
                        <th scope="col" class="py-2">Hoe lang</th>
                    </tr>
                </thead>
                <tbody class="align-top">
                    @foreach ([
                        [config('session.cookie'), 'Je sessie: een willekeurig nummer', '2 uur'],
                        ['XSRF-TOKEN', 'Beveiliging van formulieren', '2 uur'],
                        ...($ads ? [['az_consent', 'Onthoudt je keuze in de cookiebanner', '180 dagen']] : []),
                    ] as [$cookie, $use, $term])
                        <tr class="border-b border-brand-100">
                            <th scope="row" class="py-2 pr-4 font-semibold">{{ $cookie }}</th>
                            <td class="py-2 pr-4">{{ $use }}</td>
                            <td class="py-2">{{ $term }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($ads)
                <p>Als je dat goedvindt, laden we ook advertenties van een externe partij. Die partij kan zelf cookies plaatsen. Dat gebeurt pas nadat je op "Accepteren" klikt. Je kunt je keuze altijd wijzigen met "Cookie-instellingen" onderaan de pagina. Kies je daarna voor "Alleen noodzakelijke cookies", dan laden we de advertenties niet meer, maar cookies die de partij al plaatste kun je zelf verwijderen via je browserinstellingen.</p>
            @else
                <p>We plaatsen geen tracking- of marketingcookies.</p>
            @endif
        </section>

        <section class="space-y-2" aria-labelledby="logs">
            <h2 id="logs" class="text-xl font-semibold">Technische gegevens</h2>
            <p>Zodra je de site opent, maken we een sessie: een willekeurig nummer in een cookie, zonder je IP-adres of browser. Die bewaren we maximaal 2 uur, voor de werking en de beveiliging van de site. Onze server en hostingpartij leggen daarnaast tijdelijk technische gegevens vast, zoals je IP-adres en welke pagina je opvroeg, om storingen op te lossen en misbruik tegen te gaan.</p>
            <p>Bij het zoeken op kenteken en het aanvragen van een herinnering gebruiken we je IP-adres ongeveer één minuut om te veel verzoeken achter elkaar te voorkomen. Die technische regel wordt binnen een uur opgeruimd. Als je een herinnering aanvraagt, bewaren we daarnaast een uur lang een onleesbare code van je e-mailadres (geen leesbaar adres), zodat niemand je mailbox met aanvragen kan volstuuren. Foutmeldingen in ons logbestand bevatten geen e-mailadres of kenteken.</p>
        </section>

        <section class="space-y-2" aria-labelledby="affiliate">
            <h2 id="affiliate" class="text-xl font-semibold">Affiliate-links</h2>
            <p>Op sommige pagina's staan links naar andere websites, bijvoorbeeld voor een kentekenrapport of een verzekering. Als je daarop klikt en iets afneemt, ontvangen wij een vergoeding. Voor jou verandert de prijs niet. We tellen hoe vaak er op zo'n link wordt geklikt, per partner en per pagina, zonder IP-adres of andere gegevens waarmee je te herkennen bent. De partner krijgt van ons het merk, model en bouwjaar van de pagina waar je vandaan komt, nooit je kenteken. Zodra je op de link klikt, gelden de privacyregels en cookies van die website.</p>
        </section>

        <section class="space-y-2" aria-labelledby="delen">
            <h2 id="delen" class="text-xl font-semibold">Met wie delen we gegevens</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li><strong>RDW:</strong> krijgt het kenteken dat je opzoekt, en voor een APK-herinnering een controle van de vervaldatum zodra die bijna verstrijkt (staat er nog geen datum, dan eens per week). De RDW ziet daarbij het adres van onze server, niet het jouwe.</li>
                <li><strong>Onze hostingpartij:</strong> de server waarop de site en de database staan. Zij verwerken alle gegevens in deze verklaring namens ons.</li>
                <li><strong>Onze mailprovider:</strong> krijgt je e-mailadres en de inhoud van de mail (bevestiging en herinnering).</li>
                <li><strong>Partners van affiliate-links:</strong> alleen als je op hun link klikt (zie hierboven).</li>
                @if ($ads)
                    <li><strong>De advertentiepartij:</strong> alleen nadat je in de cookiebanner op "Accepteren" klikte.</li>
                @endif
            </ul>
            <p>We verkopen geen gegevens en gebruiken ze niet voor andere doelen dan hierboven staat.</p>
        </section>

        <section class="space-y-2" aria-labelledby="bewaren">
            <h2 id="bewaren" class="text-xl font-semibold">Hoe lang bewaren we gegevens</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <caption class="sr-only">Bewaartermijnen</caption>
                    <thead>
                        <tr class="border-b border-brand-600">
                            <th scope="col" class="py-2 pr-4">Gegeven</th>
                            <th scope="col" class="py-2 pr-4">Waarvoor</th>
                            <th scope="col" class="py-2 pr-4">Grondslag</th>
                            <th scope="col" class="py-2">Bewaartermijn</th>
                        </tr>
                    </thead>
                    <tbody class="align-top">
                        @foreach ([
                            ['Kenteken bij zoeken', 'De auto opzoeken', 'Gerechtvaardigd belang', 'Niet bewaard'],
                            ['Modelgegevens in de cache', 'De site snel houden', 'Gerechtvaardigd belang', '24 uur, zonder kenteken'],
                            ['Sessie (willekeurig nummer, zonder IP-adres of browser)', 'Werking en beveiliging', 'Gerechtvaardigd belang', '2 uur'],
                            ['Model, bouwjaar en nieuwprijs van de gezochte auto, in je sessie', 'Dezelfde auto op de volgende pagina\'s kunnen tonen', 'Gerechtvaardigd belang', '30 minuten, zonder kenteken'],
                            ['Onleesbare code van je e-mailadres, voor de limiet', 'Je mailbox beschermen tegen te veel mails', 'Gerechtvaardigd belang', '1 uur'],
                            ['IP-adres voor de limiet', 'Misbruik voorkomen', 'Gerechtvaardigd belang', 'Ongeveer 1 minuut actief, opgeruimd binnen een uur'],
                            ['E-mailadres, kenteken en APK-datum', 'De APK-herinnering', 'Toestemming', 'Tot je je uitschrijft; onbevestigd 7 dagen; automatisch weg een jaar na een verlopen APK, of drie maanden na je bevestiging als de RDW geen APK-datum heeft'],
                            ['Foutmeldingen in het logbestand (zonder e-mailadres en kenteken)', 'Storingen oplossen', 'Gerechtvaardigd belang', '14 dagen'],
                            ['Back-ups van de database (versleuteld)', 'Herstel na een storing', 'Gerechtvaardigd belang', '14 dagen'],
                            ['Klikken op affiliate-links', 'Statistiek', 'Gerechtvaardigd belang', 'Zonder persoonsgegevens, dus geen termijn'],
                        ] as [$item, $purpose, $basis, $term])
                            <tr class="border-b border-brand-100">
                                <th scope="row" class="py-2 pr-4 font-semibold">{{ $item }}</th>
                                <td class="py-2 pr-4">{{ $purpose }}</td>
                                <td class="py-2 pr-4">{{ $basis }}</td>
                                <td class="py-2">{{ $term }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p>Schrijf je je uit, dan verwijderen we je gegevens meteen uit de live database. In een back-up kunnen ze nog tot 14 dagen staan; daarna zijn ze ook daar verdwenen.</p>
        </section>

        <section class="space-y-2" aria-labelledby="bron">
            <h2 id="bron" class="text-xl font-semibold">Waar de cijfers vandaan komen</h2>
            <p>De cijfers komen uit de open data van de RDW (CC-0) en gaan over voertuigen, niet over personen. {{ config('site.name') }} is een onafhankelijke website en niet gelieerd aan de overheid of de RDW.</p>
        </section>

        <section class="space-y-2" aria-labelledby="rechten">
            <h2 id="rechten" class="text-xl font-semibold">Jouw rechten</h2>
            <p>Je mag vragen welke gegevens we van je hebben (inzage), ze laten corrigeren of laten verwijderen, de verwerking laten beperken en je gegevens laten overdragen. Je hebt het recht van bezwaar tegen verwerking die we op ons gerechtvaardigd belang baseren. Je kunt je toestemming op elk moment intrekken, bijvoorbeeld door je uit te schrijven via de link in een mail of door je cookie-instellingen aan te passen. Omdat we vrijwel niets van bezoekers bewaren, zal een verzoek meestal weinig opleveren.</p>
            <p>Mail je verzoek naar het contactadres hierboven. Ben je niet tevreden, dan kun je een klacht indienen bij de <a href="https://autoriteitpersoonsgegevens.nl" class="text-brand-700 underline underline-offset-2" rel="noopener">Autoriteit Persoonsgegevens</a>.</p>
        </section>
    </article>
</x-layouts.app>
