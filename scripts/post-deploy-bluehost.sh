#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-$PWD}"
APP_URL="${APP_URL:-}"

if [[ ! -d "$APP_ROOT" ]]; then
  echo "Application root not found: $APP_ROOT" >&2
  exit 1
fi

if [[ ! -f "$APP_ROOT/artisan" ]]; then
  echo "No Laravel artisan file found in $APP_ROOT" >&2
  exit 1
fi

cd "$APP_ROOT"

echo "== Bluehost post-deploy validation =="

if [[ ! -f .env ]]; then
  echo ".env is missing in $APP_ROOT" >&2
  exit 1
fi

if [[ ! -f public/build/manifest.json ]]; then
  echo "Vite manifest is missing at $APP_ROOT/public/build/manifest.json." >&2
  echo "Deploy release/public/build to both the Laravel app public/build directory and the web document root build directory." >&2
  exit 1
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php is not installed or not on PATH" >&2
  exit 1
fi

if ! php -r 'require "vendor/autoload.php"; echo "ok";' >/dev/null 2>&1; then
  echo "Composer dependencies are not installed in $APP_ROOT" >&2
  exit 1
fi

echo "-- Checking app bootstrap --"
php artisan config:cache
php artisan route:cache
php artisan view:cache

if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
  echo "-- Running database migrations --"
  php artisan migrate --force --no-interaction
else
  echo "Database is not ready yet; skipping migration step."
fi

if [[ ! -d public/storage ]] && [[ ! -L public/storage ]]; then
  echo "-- Linking storage --"
  php artisan storage:link --force || true
else
  echo "-- Storage already linked --"
fi

echo "-- Checking production URL --"
if [[ -z "$APP_URL" ]]; then
  APP_URL="$(grep -E '^APP_URL=' .env | head -n 1 | cut -d= -f2- | tr -d '\r')"
fi

if [[ -z "$APP_URL" ]]; then
  echo "APP_URL is not set in .env" >&2
  exit 1
fi

TARGET="${APP_URL%/}"
for PATH_CHECK in "/up" "/health"; do
  URL="${TARGET}${PATH_CHECK}"
  echo "GET $URL"
  if curl -fsSL "$URL" > /tmp/bluehost_post_check.out 2>/dev/null; then
    echo "Health endpoint OK: $URL"
    break
  fi
  if [[ "$PATH_CHECK" == "/health" ]]; then
    echo "No health endpoint responded successfully at $TARGET" >&2
    exit 1
  fi
done

echo "-- Checking mobile API --"
API_URL="${TARGET}/api/v1/properties"
if ! curl -fsSL -H 'Accept: application/json' "$API_URL" | php -r '$response = json_decode(stream_get_contents(STDIN), true); exit(is_array($response) && array_key_exists("data", $response) ? 0 : 1);'; then
  echo "Mobile API check failed at $API_URL" >&2
  exit 1
fi
echo "Mobile API OK: $API_URL"
echo "-- Application summary --"
php artisan about --only=environment,php,cache,queue || true

echo "Bluehost post-deploy validation finished successfully."
