#!/usr/bin/env bash
# Update de live site na een wijziging. Draai dit op de server, in de projectmap: bash scripts/deploy.sh
set -euo pipefail

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
