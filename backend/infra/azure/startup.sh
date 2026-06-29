#!/usr/bin/env bash
# Azure App Service startup script (Linux PHP 8.2)
set -euo pipefail

cd /home/site/wwwroot

echo "[startup] EFES DMS boot $(date -u +%Y-%m-%dT%H:%M:%SZ)"

MOUNT_PATH="${AZURE_FILES_MOUNT_PATH:-/home/laravel-storage}"
if [ -d "$MOUNT_PATH" ]; then
  mkdir -p storage/app
  if [ ! -L storage/app/private ] && [ ! -d storage/app/private ]; then
    ln -sf "$MOUNT_PATH" storage/app/private
    echo "[startup] Linked $MOUNT_PATH -> storage/app/private"
  fi
else
  echo "[startup] WARN: Azure Files mount not found at $MOUNT_PATH"
  mkdir -p storage/app/private
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

if [ -f composer.json ] && [ ! -f vendor/autoload.php ]; then
  echo "[startup] Running composer install..."
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
fi

if [ -f artisan ]; then
  php artisan migrate --force --no-interaction || echo "[startup] WARN: migrate failed"
  php artisan storage:link --force 2>/dev/null || true
  php artisan config:cache --no-interaction || true
  php artisan route:cache --no-interaction || true
  php artisan view:cache --no-interaction || true

  if [ -n "${PRODUCTION_ADMIN_EMAIL:-}" ]; then
    php artisan db:seed --class=ProductionBootstrapSeeder --force --no-interaction 2>/dev/null || true
  fi
fi

if ! pgrep -f "artisan queue:work" > /dev/null 2>&1; then
  nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /home/LogFiles/queue.log 2>&1 &
fi

if ! pgrep -f "schedule-loop" > /dev/null 2>&1; then
  (
    while true; do
      php artisan schedule:run --no-interaction >> /home/LogFiles/scheduler.log 2>&1 || true
      sleep 60
    done
  ) >> /home/LogFiles/scheduler-loop.log 2>&1 &
fi

echo "[startup] Ready"
