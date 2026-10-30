# Live gaan met Auto Zwaktes

Een checklist, in de volgorde waarin je het doet. Controleer onderweg met `php artisan site:check`: dat commando zegt wat nog niet klopt.

## 1. Eerst regelen
- [ ] **Domein** (bijvoorbeeld autozwaktes.nl) en eventueel ook een variant zonder streepje of met streepje, en laat die doorsturen.
- [ ] **E-mailadres** voor de site, bijvoorbeeld `info@jouwdomein.nl` (komt op de privacypagina als `CONTACT_EMAIL`) en een verzendadres voor de APK-herinnering.
- [ ] **Mailprovider** met SMTP (de meeste hosters en diensten als Mailgun, Postmark of Brevo hebben dit). Zet SPF en DKIM goed bij je domein, anders komen de herinneringen in de spam.
- [ ] **Affiliate-accounts** (kentekenrapport, verzekering, onderdelen). Zonder links verdient de site niets.
- [ ] **KvK-inschrijving** is meestal nodig zodra je geld verdient met affiliate-links of advertenties. Vraag dit na bij de Belastingdienst of KvK.

## 2. Hosting
**Wat de site nodig heeft.** Een gewone webhosting (shared hosting) is niet genoeg. Je hebt nodig:
- SSH-toegang en PHP 8.4, MySQL 8, Composer, Node (om één keer de assets te bouwen)
- een **wachtrij-worker die blijft draaien** (de RDW-import duurt uren)
- een **cron-taak** die elke minuut `php artisan schedule:run` doet (wekelijkse import, dagelijkse APK-mails, nachtelijke backup)
- genoeg geheugen en schijf: de database wordt met 20 merken ongeveer 5 à 10 GB, en de import is zwaar voor MySQL. Op mijn eigen laptop met 2 GB crashte de database tijdens de import.

**Mijn advies: een VPS (virtuele server) in Europa**, met minimaal 2 vCPU, **4 GB RAM (liever 8 GB)** en **40 GB schijf (liever 80 GB)**. Aanbieders zoals [Hetzner Cloud](https://www.hetzner.com/cloud) zijn daarvoor populair en betaalbaar; de prijzen zijn in 2026 meerdere keren gestegen, dus kijk op hun site voor het actuele bedrag. Reken op orde van grootte een paar tot ruim tien euro per maand voor de server. Een server met 8 GB mag je na de eerste grote import terugzetten naar een kleinere als de weekupdates goed blijven lopen.

**Beheren zonder zelf een server in te richten.** Een dienst als [Laravel Forge](https://forge.laravel.com) of [Ploi](https://ploi.io) richt de server voor je in (PHP, MySQL, nginx, HTTPS, wachtrij-worker, scheduler) en deployt vanuit GitHub. Dat kost extra per maand (ongeveer 8 tot 12 euro volgens de prijslijsten die ik vond; kijk op hun site voor het actuele bedrag), maar bespaart je veel werk en fouten. Voor een student is dit de aanbevolen weg.

**Zelf doen kan ook**: Ubuntu-server, nginx, PHP-FPM, MySQL, Let's Encrypt (certbot) en supervisor.

## 3. De site installeren
```bash
git clone https://github.com/Bram79/Autozwaktes.git
cd Autozwaktes
cp .env.production.example .env        # en vul .env in
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=StarterModelsSeeder      # de startmerken en -modellen
php artisan make:filament-user                       # jouw beheeraccount
php artisan optimize
php artisan site:check
```
- Laat de webserver wijzen naar de map `public/`.
- Zet **HTTPS** aan (Let's Encrypt; bij Forge of Ploi is dat één klik). `APP_URL` moet met `https://` beginnen.
- Zet `ADMIN_EMAILS` op jouw e-mailadres, anders komt niemand in `/admin`.

## 4. Wachtrij en scheduler
**Cron** (via `crontab -e` als de gebruiker van de site):
```
* * * * * cd /var/www/autozwaktes && php artisan schedule:run >> /dev/null 2>&1
```
**Wachtrij-worker met supervisor** (`/etc/supervisor/conf.d/autozwaktes-worker.conf`):
```ini
[program:autozwaktes-worker]
command=php /var/www/autozwaktes/artisan queue:work --sleep=3 --tries=3 --timeout=0
directory=/var/www/autozwaktes
user=www-data
autostart=true
autorestart=true
numprocs=1
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=/var/www/autozwaktes/storage/logs/worker.log
```
Daarna: `sudo supervisorctl reread && sudo supervisorctl update`. Forge en Ploi doen dit met een paar klikken.

## 5. De eerste data
In deze volgorde, op de server (duurt samen enkele uren; de worker moet draaien):
```bash
php artisan rdw:brands --top=20 --add     # de grootste merken
php artisan rdw:update                    # importeert alles en berekent de cijfers
php artisan rdw:discover                  # maakt modellen aan
php artisan rdw:types                     # typecodes en nieuwprijzen
php artisan generations:propose           # voorstellen voor generaties
```
Open dan **/admin**, bekijk de *Generatievoorstellen* en keur ze goed, pas ze toe en draai `php artisan rdw:calculate`. Daarna heb je een volledige site. Het is verstandig om de eerste import op de livegang-server te doen, voordat je de site bekend maakt.

## 6. Google Search Console
1. Ga naar [Search Console](https://search.google.com/search-console) en voeg je domein toe als "URL-voorvoegsel".
2. Kies de verificatiemethode **HTML-tag**. Je krijgt `<meta name="google-site-verification" content="CODE">`.
3. Zet alleen `CODE` in `.env` als `GOOGLE_SITE_VERIFICATION=CODE`, draai `php artisan optimize:clear` en klik op *Verifiëren*.
4. Dien onder *Sitemaps* in: `https://jouwdomein.nl/sitemap.xml`.
5. Kijk na een paar dagen bij *Pagina's* of ze geïndexeerd worden. Pagina's met te weinig data staan bewust op `noindex`.

## 7. Backups
- `php artisan backup:database` maakt elke nacht om 02:30 een gecomprimeerde kopie in `storage/app/backups` (de laatste 14 dagen). De grote importtabellen laat het weg: die kun je opnieuw ophalen bij de RDW. Dit staat alleen in de scheduler als `APP_ENV=production`.
- **Zet de backups óók ergens anders neer.** Een backup op dezelfde server helpt niet als de server weg is. Kies de automatische **server-snapshots** van je hoster (kost meestal een paar euro) en/of kopieer `storage/app/backups` naar andere opslag met bijvoorbeeld `rclone`.
- **Terugzetten:** `gunzip < backup.sql.gz | mysql -u gebruiker -p autozwaktes`. Daarna `php artisan rdw:update` om de importtabellen te vullen.
- Test minstens één keer dat je een backup echt kunt terugzetten.

## 8. Updates en onderhoud
- Een nieuwe versie live zetten: `bash scripts/deploy.sh` (haalt de code op, bouwt, migreert, ververst de cache en herstart de worker).
- **Fouten** staan in `storage/logs/laravel-*.log`. Bezoekers zien een nette 404- of 500-pagina, zonder technische informatie.
- **Bereikbaarheid:** `https://jouwdomein.nl/up` geeft een 200 als de site draait. Laat een gratis monitor (bijvoorbeeld UptimeRobot) die adres elke 5 minuten controleren en je mailen als hij niet antwoordt.
- De wekelijkse import loopt maandag om 03:00. Kijk af en toe in het dashboard bij *Laatste import* of dat klopt.

## 9. Laatste check voor je het deelt
- [ ] `php artisan site:check` geeft geen fouten.
- [ ] De privacyverklaring is door jou gelezen (en eventueel door iemand met verstand van privacywetgeving), `CONTACT_EMAIL` is ingevuld.
- [ ] Je hebt minstens van de populairste modellen een eigen koopadvies of een goedgekeurd concept.
- [ ] Een APK-herinnering aangevraagd met je eigen e-mailadres: bevestiging komt aan, en de uitschrijflink werkt.
- [ ] Je ziet in `/admin/affiliate-clicks` dat een klik op een affiliate-link geteld wordt.
- [ ] Zet de advertenties pas aan (`ADS_ENABLED=true`) als je een account hebt; de cookiebanner verschijnt dan vanzelf.
