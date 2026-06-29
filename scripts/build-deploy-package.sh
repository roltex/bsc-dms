#!/usr/bin/env bash
# Build frontend and create deploy.zip for Azure App Service.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUTPUT="${OUTPUT:-$ROOT/deploy.zip}"
BACKEND="$ROOT/backend"

if [ ! -d "$ROOT/frontend/dist" ]; then
  echo "ERROR: frontend/dist not found. Run: cd frontend && npm ci && npm run build"
  exit 1
fi

if [ ! -f "$BACKEND/vendor/autoload.php" ]; then
  echo "ERROR: backend/vendor not found. Run: cd backend && composer install --no-dev --optimize-autoloader"
  exit 1
fi

echo "==> Merging frontend build into backend/public/"
cp "$ROOT/frontend/dist/index.html" "$BACKEND/public/index.html"
mkdir -p "$BACKEND/public/assets"
cp -r "$ROOT/frontend/dist/assets/." "$BACKEND/public/assets/"
if [ -f "$ROOT/frontend/dist/vite.svg" ]; then
  cp "$ROOT/frontend/dist/vite.svg" "$BACKEND/public/vite.svg"
fi

chmod +x "$BACKEND/infra/azure/startup.sh" "$BACKEND/infra/azure/post-deploy.sh" 2>/dev/null || true

echo "==> Creating $OUTPUT"
rm -f "$OUTPUT"
(
  cd "$BACKEND"
  zip -rq "$OUTPUT" . \
    -x '.env' \
    -x '.env.*' \
    -x '!.env.azure.example' \
    -x 'node_modules/*' \
    -x 'tests/*' \
    -x 'storage/logs/*' \
    -x 'storage/framework/cache/data/*' \
    -x 'storage/framework/sessions/*' \
    -x 'storage/framework/views/*' \
    -x '.git/*' \
    -x 'database/database.sqlite'
)

echo "==> Package ready: $OUTPUT"
