#!/usr/bin/env bash
# Update de live site na een wijziging. Draai dit op de server, in de projectmap: bash scripts/deploy.sh
set -euo pipefail

# If a step fails, do not leave the site in maintenance mode: put it back up and say clearly that the deploy failed.
trap 'echo "DEPLOY MISLUKT: zie de fout hierboven. De site wordt weer aangezet; controleer of de code en de database kloppen." >&2; php artisan up' ERR

php artisan down --retry=60 || true

git pull --ff-only
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
