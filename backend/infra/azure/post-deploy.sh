#!/usr/bin/env bash
set -euo pipefail
cd /home/site/wwwroot
echo "[post-deploy] $(date -u +%Y-%m-%dT%H:%M:%SZ)"
if [ -f artisan ]; then
  php artisan migrate --force --no-interaction || true
  php artisan config:cache --no-interaction || true
  php artisan route:cache --no-interaction || true
  php artisan view:cache --no-interaction || true
fi
echo "[post-deploy] Done"
