#!/usr/bin/env bash
# Update de live site na een wijziging. Draai dit op de server, in de projectmap: bash scripts/deploy.sh
set -euo pipefail

# If a step fails: before the new code is pulled nothing has changed, so the site goes back up. After that the site stays in
# maintenance mode: new code on an old database (or half-installed packages) must not be live. Fix the problem, then run
# `php artisan up`, or run this script again.
CODE_CHANGED=0
trap 'echo "DEPLOY MISLUKT: zie de fout hierboven." >&2; if [ "$CODE_CHANGED" = "0" ]; then php artisan up; else echo "De site blijft in onderhoudsmodus. Los het op en draai daarna: php artisan up" >&2; fi' ERR

php artisan down --retry=60 || true

git pull --ff-only
CODE_CHANGED=1
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

php artisan migrate --force
php artisan filament:upgrade

# snelle versies van de configuratie, routes en views
php artisan optimize
php artisan view:cache

# de wachtrij-worker moet de nieuwe code laden
php artisan queue:restart

php artisan up
php artisan site:check || true
