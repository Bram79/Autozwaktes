# Toekomstige aanpassingen

Notitie van 17 november 2026. Dit is nog niet gebouwd: het is wat we samen hebben bedacht, zodat het later kan worden opgepakt. Elke keuze staat erbij als die van jou (besloten in het gesprek) of als mijn voorstel.

## 1. Waarom

De site moet rustiger en menselijker aanvoelen en minder "gegenereerd". Drie dingen vallen nu op:

- de footer is leeg: één regel met "Privacy", twee menulinks en twee kleine alinea's;
- de ranglijsten hebben te veel keuzes (bouwjaar en brandstof);
- "Score (100 = gemiddeld)" met "48% beter" leest als jargon en is niet vlot af te lezen.

## 2. Wat we willen en niet willen

**Doel**
- Een vollere, nuttige footer.
- Een ranglijst met alleen "alle auto's" en "per merk".
- Een score die iedereen zonder uitleg kan aflezen.

**Geen doel**
- Geen nieuwe functies of pagina's, en geen nieuw ontwerp van de rest van de site.

**Mislukt als**
- er kentekens of e-mailadressen op elke pagina worden gevraagd of bewaard (bijvoorbeeld door een formulier in de footer);
- er scripts van andere bedrijven komen (deelknoppen, ingebouwde feeds);
- een foutpagina stukgaat omdat de footer de database nodig heeft;
- er teksten of getallen op de site komen die niet uit de database of de config komen.

## 3. Wat er verandert

### 3.1 Ranglijst: alleen alle auto's en per merk (jouw keuze)
Brandstof en bouwjaar verdwijnen echt (niet alleen verbergen). De site staat nog niet online, dus er zijn geen oude adressen om om te leiden.

Raakt volgens mijn verkenning:
- `routes/web.php`: de route `ranking` accepteert nu `alles|merk|brandstof|bouwjaar`;
- `app/Http/Controllers/RankingController.php`: de `match` per soort;
- `app/Site/RankingService.php`: `scores()` heeft nu parameters voor brandstof en bouwjaar, en `fuels()` en `years()` bestaan alleen voor de keuzelijsten;
- `resources/views/pages/rankings.blade.php`, `pages/ranking.blade.php` en `partials/ranking-links.blade.php` (de blokken "Per brandstof" en "Per bouwjaar");
- de sitemap, als daar ranglijsten in staan (nog uitzoeken waar die adressen worden gemaakt);
- tests: `HonestRankingTest`, `ClaimsAndWordingTest`, `RankingAndComparisonTest` en `AdviceTest` bevatten verwijzingen naar brandstof en bouwjaar. Pas een test alleen aan als het gedrag echt verdwijnt, en laat de rest staan.

### 3.2 Het balkje bij de score (jouw wens, mijn uitwerking)
- Het getal staat links met een `%`. Het balkje loopt naar rechts en eindigt bij 100%, of bij het hoogste getal van de lijst als dat hoger is (bij "De meeste gebreken" zijn scores boven 100).
- Een dun streepje markeert 100%. Wat boven 100% uitkomt, krijgt een andere kleur.
- "48% beter" en "(100 = gemiddeld)" verdwijnen. In de kolomkop staat één keer "gemiddeld" bij de 100% (mijn voorstel voor de kolomnaam: "Gebreken t.o.v. gemiddeld").
- De drie alinea's uitleg boven de tabel worden korter en zonder "100 = gemiddeld". De getallen daarin (bouwjaren, minimaal aantal auto's) blijven uit de data en de config komen.
- Geldt voor beide lijsten. De bestaande stijl van de tabel blijft, alleen de manier waarop de score wordt getoond verandert (`score-track` en `score-fill` in `resources/css/app.css`).

### 3.3 Footer (jouw keuze: variant met populaire modellen)
Een aparte Blade-component (nu staat de footer in `resources/views/components/layouts/app.blade.php`, regel 64 tot 74).

- **Links:** logo, naam en één zin over Nuvik, met onder het logo een knop "Herinner mij" naar `/apk-herinnering` (mijn keuze: een gewone link, geen formulier, zodat er geen kenteken- of e-mailveld op elke pagina staat).
- **Midden:** "Ontdek" met Ranglijsten, Alle auto's en APK-herinnering, en "Populaire modellen" met een korte lijst uit `StatsService::popularModels()`. Die wordt al onthouden in de cache, dus het kost geen extra query. (Mijn keuze: "Populaire merken" uit het schetsje laat ik weg, want er is geen overzichtspagina van merken.)
- **Rechts:** Over Nuvik met Privacy, Bron (RDW) en Cookie-instellingen (alleen als advertenties of de cookiebanner aan staan, zoals nu), daaronder Contact en socials.
- **Onderaan:** de twee waarschuwingen die er nu al staan, in een rustige balk, met het jaartal.
- **Contact:** een gewone link met het adres uit `CONTACT_EMAIL` (jouw keuze: geen contactformulier). Staat er geen adres in, dan verschijnt er niets.
- **Socials (jouw keuze: Instagram, TikTok en Facebook):** gewone links met een klein icoon die in een nieuw tabblad openen, met `rel="noopener"`. Elke social krijgt een instelling in `config/site.php` en `.env.example` en `.env.production.example`. Is er geen adres ingevuld, dan staat het icoon er niet. Ik weet nog geen adressen, dus die verzin ik niet.
- **Veilig:** als de lijst met modellen niet geladen kan worden (bijvoorbeeld op een foutpagina), laat de footer die kolom weg en gaat de pagina niet stuk.
- Alle teksten in het Nederlands. Elke kleur en maat via de bestaande tokens in `resources/css/app.css`.

## 4. Aanpakken die we hebben overwogen

| Onderdeel | Gekozen | Afgewezen |
|---|---|---|
| Ranglijst | Brandstof en bouwjaar echt weghalen | Alleen de links verbergen: laat dode code achter |
| Balkje | Eén schaal met streepje op 100% | (geen echt alternatief) |
| Footer | Met populaire modellen | Alleen vaste links: blijft net zo leeg |

## 5. Wat een bouwer nog moet uitzoeken of beslissen

- Waar de sitemap ranglijst-adressen maakt, en of daar iets uit moet.
- De precieze zin onder het logo en de tekst van de kolomnamen (Nederlands, kort).
- De iconen voor Instagram, TikTok en Facebook (als losse SVG in de views; geen nieuw pakket zonder overleg).
- De privacypagina nakijken: gewone links naar sociale netwerken zetten geen cookies, maar controleer of de tekst nog klopt.

## 6. Nog te doen buiten de code

- De accounts op Instagram, TikTok en Facebook aanmaken en de adressen in `.env` zetten.
- `CONTACT_EMAIL` invullen zodra het domein bestaat.
