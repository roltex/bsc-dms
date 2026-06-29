#!/usr/bin/env bash
# Azure App Service startup script (Linux PHP 8.2)
set -euo pipefail

cd /home/site/wwwroot

echo "[startup] EFES DMS boot $(date -u +%Y-%m-%dT%H:%M:%SZ)"

# Mount Azure Files share to Laravel private storage
MOUNT_PATH="${AZURE_FILES_MOUNT_PATH:-/home/laravel-storage}"
if [ -d "$MOUNT_PATH" ]; then
  mkdir -p storage/app
  if [ ! -L storage/app/private ] && [ ! -d storage/app/private ]; then
    ln -sf "$MOUNT_PATH" storage/app/private
    echo "[startup] Linked $MOUNT_PATH -> storage/app/private"
  elif [ -L storage/app/private ]; then
    echo "[startup] Azure Files mount already linked"
  fi
else
  echo "[startup] WARN: Azure Files mount not found at $MOUNT_PATH — using local storage"
  mkdir -p storage/app/private
fi

mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

if [ -f composer.json ] && [ ! -d vendor/autoload.php ] && [ ! -f vendor/autoload.php ]; then
  echo "[startup] Running composer install..."
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
fi

if [ -f artisan ]; then
  php artisan migrate --force --no-interaction || echo "[startup] WARN: migrate failed (DB may not be ready yet)"
  php artisan storage:link --force 2>/dev/null || true
  php artisan config:cache --no-interaction || true
  php artisan route:cache --no-interaction || true
  php artisan view:cache --no-interaction || true

  # Bootstrap production admin if env vars are set and no users exist
  if [ -n "${PRODUCTION_ADMIN_EMAIL:-}" ]; then
    php artisan db:seed --class=ProductionBootstrapSeeder --force --no-interaction 2>/dev/null || true
  fi
fi

# Background queue worker
if ! pgrep -f "artisan queue:work" > /dev/null 2>&1; then
  echo "[startup] Starting queue worker..."
  nohup php artisan queue:work --sleep=3 --tries=3 --max-time=3600 >> /home/LogFiles/queue.log 2>&1 &
fi

# Scheduler loop (runs schedule:run every 60 seconds)
if ! pgrep -f "schedule-loop" > /dev/null 2>&1; then
  echo "[startup] Starting scheduler loop..."
  (
    while true; do
      php artisan schedule:run --no-interaction >> /home/LogFiles/scheduler.log 2>&1 || true
      sleep 60
    done
  ) >> /home/LogFiles/scheduler-loop.log 2>&1 &
  echo $! > /tmp/schedule-loop.pid
fi

echo "[startup] Ready"
