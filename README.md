# Auto Zwaktes

APK-gebreken per automodel en bouwjaar, op basis van RDW open data (CC-0).

## RDW-import

Draai altijd een queue worker (`php artisan queue:work`) naast de commando's.

| Commando | Doet |
|---|---|
| `php artisan rdw:update` | Volledige import: gebrekcodes, voertuigen, brandstof, gebreken, daarna statistieken herberekenen. Staat wekelijks in de scheduler (maandag 03:00). |
| `php artisan rdw:import {dataset}` | Eén dataset: `defect_codes`, `vehicles`, `fuel` of `defects`. |
| `php artisan rdw:calculate [--now]` | Statistieken opnieuw berekenen en cache legen. |
| `php artisan rdw:unmatched` | Toont RDW-modelnamen die bij geen enkel model passen (om aliassen aan te vullen). |
| `php artisan rdw:discover [--min=1000] [--dry-run]` | Maakt automatisch modellen aan uit de geïmporteerde voertuigen, met aliassen en één generatie "Alle bouwjaren" (daarna zelf opsplitsen in de admin). Bestaande modellen blijven ongemoeid. |
| `php artisan rdw:types` | Haalt per model de RDW-typecodes per bouwjaar op (een typecode verandert meestal bij een nieuwe generatie). Duurt een halve minuut. |
| `php artisan generations:propose [--model=polo] [--no-wikidata] [--auto-approve=90] [--dry-run]` | Maakt voorstellen voor generaties uit de typecodes en Wikidata, met een zekerheidspercentage. Niets komt live: keur ze goed in de admin onder "Generatievoorstellen". Modellen met handgemaakte generaties blijven met rust (tenzij `--include-existing`). |
| `php artisan generations:apply` | Zet goedgekeurde voorstellen op de site (kan ook met de knop in de admin). Draai daarna `rdw:calculate`. |
| `php artisan advice:draft [--model=polo] [--limit=10]` | Optioneel: Claude schrijft concept-koopadviezen uit onze eigen cijfers (zet `AI_ADVICE_ENABLED=true` en `ANTHROPIC_API_KEY` in `.env`). Een tekst met een getal dat niet in de cijfers staat wordt automatisch afgewezen. Concepten komen pas live na goedkeuren in de admin onder "Koopadvies-concepten". |
| `php artisan db:seed --class=ModelFixesSeeder` | Ruimt de modellen van `rdw:discover` op: voegt dubbelen samen, past namen aan en zet echte generaties bij de populairste modellen (jaartallen zijn een eerste gok, controleer ze). Draai dit na `rdw:discover` en vóór `rdw:calculate`. |

Scheduler lokaal: `php artisan schedule:work`. Fouten staan in `storage/logs/laravel.log` en in de tabel `import_runs`.

## Beheer (`/admin`)

Gebouwd met Filament. Maak een beheerder aan met:

```bash
php artisan make:filament-user
```


Zet ook `CONTACT_EMAIL` in `.env`: dat adres staat op de privacypagina (`/privacy`).
Buiten de lokale omgeving komen alleen e-mailadressen uit `ADMIN_EMAILS` (in `.env`, kommagescheiden) binnen.

Hier beheer je generaties (incl. koopadvies), modellen, merken, modelnaam-patronen en affiliate-instellingen. Elke wijziging leegt de site-cache. Wijzig je bouwjaren van een generatie of een patroon, klik dan op "Statistieken herberekenen" (queue worker nodig).
