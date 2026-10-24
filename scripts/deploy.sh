#!/usr/bin/env bash
# Runs on the server (called by the GitHub Actions deploy workflow).
# Puts the site in maintenance mode, updates code and database, rebuilds caches,
# restarts the queue worker, and brings the site back up. Stops at the first error.
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Maintenance mode on"
php artisan down --retry=15 || true

# Always bring the site back up, even if a step below fails.
trap 'php artisan up' EXIT

echo "==> Pulling latest code"
git fetch --quiet origin main
git reset --hard origin/main

echo "==> Installing PHP packages"
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-progress

echo "==> Running database migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting queue workers (they reload the new code)"
php artisan queue:restart

echo "==> Done"
