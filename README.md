# Autozwaktes.nl

APK-gebreken per automodel en bouwjaar, op basis van RDW open data (CC-0).

## RDW-import

Draai altijd een queue worker (`php artisan queue:work`) naast de commando's.

| Commando | Doet |
|---|---|
| `php artisan rdw:update` | Volledige import: gebrekcodes, voertuigen, brandstof, gebreken, daarna statistieken herberekenen. Staat wekelijks in de scheduler (maandag 03:00). |
| `php artisan rdw:import {dataset}` | Eén dataset: `defect_codes`, `vehicles`, `fuel` of `defects`. |
| `php artisan rdw:calculate [--now]` | Statistieken opnieuw berekenen en cache legen. |
| `php artisan rdw:unmatched` | Toont RDW-modelnamen die bij geen enkel model passen (om aliassen aan te vullen). |

Scheduler lokaal: `php artisan schedule:work`. Fouten staan in `storage/logs/laravel.log` en in de tabel `import_runs`.

## Beheer (`/admin`)

Gebouwd met Filament. Maak een beheerder aan met:

```bash
php artisan make:filament-user
```

Buiten de lokale omgeving komen alleen e-mailadressen uit `ADMIN_EMAILS` (in `.env`, kommagescheiden) binnen.

Hier beheer je generaties (incl. koopadvies), modellen, merken, modelnaam-patronen en affiliate-instellingen. Elke wijziging leegt de site-cache. Wijzig je bouwjaren van een generatie of een patroon, klik dan op "Statistieken herberekenen" (queue worker nodig).
