# Auto Zwaktes

Laravel 13 + Livewire 4 + Tailwind 4 + Filament 5 (admin) + Pest 4, PHP 8.4. De site laat per automodel, generatie
en bouwjaar de meest voorkomende APK-gebreken zien, berekend uit RDW open data (CC-0). Eigenaar is een student:
Nederlands in gesprek, Engels in code.

## Commando's
Draai alles met `php artisan ...` vanuit de projectroot. De database draait in DDEV/Colima op `127.0.0.1:33066`
(`DB_HOST`/`DB_PORT` in `.env`); de tests gebruiken SQLite in het geheugen en hebben geen database nodig.

### Ontwikkelen en testen
- `php artisan serve` – site op http://localhost:8000 (admin op `/admin`).
- `npm run dev` – Vite met hot reload. `npm run build` – assets bouwen (nodig voor productie en `site:check`).
- `php artisan test` – volledige Pest-suite. Eén bestand: `php artisan test tests/Feature/SiteTest.php`. Eén test: `--filter="naam van de test"`.
- `./vendor/bin/pint` – code formatteren. `./vendor/bin/pint --test` – alleen controleren (zo draait de pre-push hook).
- `php artisan queue:work --timeout=0` – wachtrij-worker. Nodig voor imports; `--timeout=0` omdat stukken lang duren.
- `php artisan schedule:work` – scheduler lokaal (`rdw:update` maandag 03:00, `apk:remind` dagelijks 08:00).
- `composer dev` werkt niet (verwijst naar een `artisan dev` die niet bestaat); gebruik `serve` en `npm run dev` apart.

### RDW-gegevens (in deze volgorde bij een nieuw merk of een verse database)
- `rdw:brands [--top=20] [--add]` – toont de grootste merken in de RDW-data; `--add` voegt ontbrekende merken toe.
- `rdw:update` – volledige import (gebrekcodes, voertuigen, brandstof, gebreken) en daarna statistieken. Duurt uren; worker moet draaien.
- `rdw:import {dataset}` – één dataset: `defect_codes`, `vehicles`, `fuel` of `defects` (zonder herberekenen).
- `rdw:discover [--min=1000] [--dry-run]` – maakt modellen aan uit de voertuigen (met alias en één generatie).
- `db:seed --class=ModelFixesSeeder` – ruimt modellen op die `rdw:discover` maakte (alleen voor de eerste 8 merken).
- `rdw:types` – haalt typecodes en nieuwprijzen per model en jaar op (invoer voor generaties en vergelijkingen).
- `generations:propose [--model=slug] [--dry-run]` – voorstellen voor generaties; goedkeuren in admin of `generations:apply`.
- `rdw:calculate [--now]` – statistieken herberekenen en cache legen. Altijd nodig na nieuwe generaties.
- `rdw:unmatched` – RDW-modelnamen zonder model (om aliassen aan te vullen).

### Overig
- `advice:draft` – optionele AI-concepten voor koopadvies (uit; `AI_ADVICE_ENABLED`).
- `apk:remind` – verstuurt APK-herinneringen. `backup:database` – database-backup. `site:check` – is de site klaar voor livegang?
- Livegang: zie `docs/LIVEGANG.md`; update op de server met `bash scripts/deploy.sh`.

## Testen
- Bij elke nieuwe feature of grotere wijziging schrijf je Pest-tests die het gedrag controleren (in `tests/Feature` of `tests/Unit`).
- Bij een bugfix schrijf je eerst een test die de bug laat zien en faalt; pas daarna los je de bug op.
- Draai na elke wijziging de relevante tests. Draai vóór elke commit `php artisan test` (alles) en `./vendor/bin/pint --test`.
- Een taak is pas klaar als alle tests groen zijn. Pas nooit een test aan om hem te laten slagen: los het echte probleem op of vraag het mij.
- Tests praten nooit met internet: gebruik `Http::fake()` voor RDW, Wikidata en Anthropic, `Mail::fake()` voor mail, `Process::fake()` voor `mysqldump`.
- Feature-tests gebruiken `RefreshDatabase` (staat in `tests/Pest.php`); begin ze met `$this->withoutVite()`.
- Gebruik geen tijdelijke debug-tests in de repo; verwijder ze na gebruik.

## Git
- Commit-berichten in het Engels, kort en duidelijk, Conventional Commits: `feat:`, `fix:`, `test:`, `refactor:`, `docs:`, `style:`, `chore:`.
- Eén onderwerp per commit; formatteer-wijzigingen (Pint) in een eigen `style:`-commit.
- Grotere taken op een feature-branch (`feat/naam`); merge pas naar `main` als alle tests groen zijn.
- Push alleen als ik het vraag. Gebruik nooit `--no-verify`; de pre-push hook (`.githooks/pre-push`) draait Pint en alle tests.
- Commit nooit `.env`, API-keys of andere geheimen. Nieuwe instellingen komen in `.env.example` en `.env.production.example`.

## Laravel-afspraken
- Code, variabelen, tabel- en kolomnamen in het Engels; alle teksten op de site en in de admin in het Nederlands.
- `env()` alleen in `config/*.php`; overal elders `config()`. Nieuwe instellingen gaan in `config/site.php` of `config/rdw.php`.
- Valideer invoer met Form Requests of Livewire-validatie (`$request->validate(...)` is acceptabel in een kleine controller); gebruik nooit ongevalideerde invoer.
- Pas nooit een migratie aan die al gedraaid is (of gepusht): maak een nieuwe migratie.
- Geen zware queries tijdens een paginabezoek: lees uit de voorberekende tabellen (`vehicle_counts`, `defect_stats`, `model_prices`, `vehicle_types`) en cache via `StatsService`/`RankingService`/`ComparisonService`.
- In de cache alleen gewone arrays, geen Eloquent-modellen of objecten (ze komen kapot terug). Cache legen gebeurt in `rdw:calculate` en bij het opslaan van modellen die `FlushesSiteCache` gebruiken.
- Voorkom N+1: gebruik `with()` bij lijsten. Nieuwe lijst met modellen? Controleer het aantal queries.
- Houd controllers dun: logica hoort in `app/Site`, `app/Rdw`, `app/Generations`, `app/Ai` of `app/Wikidata`.
- Een pad in de URL dat een merk kan zijn, moet in `$reserved` in `routes/web.php` als het een eigen route heeft.
- Vraag mij eerst voordat je een nieuw Composer- of npm-pakket toevoegt.

## Structuur in het kort
- `app/Rdw` – RDW-client, import (`PageImporter`), `StatisticsCalculator`, `ModelNormalizer` (RDW-naam → model via alias-regex), `ModelDiscoverer`, `BrandAdder`.
- `app/Jobs` – import in stukken van 50.000 rijen via `Bus::batch`; `PlanDatasetImportJob` start de volgende stap.
- `app/Site` – wat bezoekers zien: `StatsService`, `AdviceWriter`, `RankingService`, `ComparisonService`, `PlateLookup`, `AffiliateLink`, `Seo`.
- `app/Livewire` (zoeken, filters), `app/Filament` (admin), `resources/views` (Blade, Tailwind; kleuren in `resources/css/app.css`).
- `app/Generations` en `app/Wikidata` – voorstellen voor generaties; `app/Ai` – optionele concept-koopadviezen met getallencontrole.
- Tabellen `staging_*` zijn tijdelijke importdata; de site leest ze nooit. Instellingen uit de admin staan in de tabel `settings`.

## Privacy en inhoud
- Sla kentekens nooit op en log ze niet. Enige uitzondering: de APK-herinnering (`apk_reminders`), alleen met dubbele opt-in, zoals in `resources/views/pages/privacy.blade.php` staat. Pas die tekst aan bij elke wijziging in wat we bewaren.
- Kentekens gaan via POST, niet in een URL. Affiliate-klikken bewaren geen IP-adres of andere persoonsgegevens.
- Toon alleen geaggregeerde cijfers (minimaal `site.min_sample` auto's), nooit data per kenteken. Enige uitzondering: direct na een kentekenzoekopdracht tonen we de catalogusprijs (nieuwprijs) van die ene auto aan de bezoeker zelf. Alleen het bedrag blijft 30 minuten in de sessie en een dag in de cache onder een hmac-sleutel, nooit het kenteken zelf (zie `PlateVisit`).
- Alle getallen in teksten komen uit de database. Voeg geen feiten toe die niet in de data staan (geen motorproblemen, prijzen of reputatie uit het hoofd).
- Scrape geen commerciële sites en neem geen teksten van andere sites over. Koopadvies is van de eigenaar of een goedgekeurd concept.
- Gebruik de naam en het logo van de RDW niet zo dat het lijkt alsof de site van de overheid is; de footer zegt dat de site onafhankelijk is.

## Werkwijze
- Bij grotere taken: maak eerst een kort plan en wacht op mijn akkoord.
- Als iets onduidelijk is, vraag het in plaats van te gokken.
- Houd code simpel en leesbaar; ik ben student en wil alles begrijpen. Leg niet-voor-de-hand-liggende keuzes uit in een korte comment of in je antwoord.
- Als ik je twee keer op hetzelfde moet wijzen, stel dan voor om een regel aan dit bestand toe te voegen.
- Zeg eerlijk wat je niet hebt kunnen controleren (bijvoorbeeld iets dat alleen met echte RDW-data of een echte mailserver kan).
