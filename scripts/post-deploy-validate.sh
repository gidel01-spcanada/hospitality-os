#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="${1:-${PWD}}"

if [[ ! -d "$APP_ROOT" ]]; then
  echo "Application root not found: $APP_ROOT" >&2
  exit 1
fi

if [[ ! -f "$APP_ROOT/artisan" ]]; then
  echo "No Laravel artisan file found in $APP_ROOT" >&2
  exit 1
fi

cd "$APP_ROOT"

echo "== Post-deploy validation for $APP_ROOT =="

echo "-- PHP check --"
php -v | head -n 1

echo "-- Environment check --"
if [[ ! -f .env ]]; then
  echo ".env file is missing. Add the production environment file before validation." >&2
  exit 1
fi

# Ensure the app can boot and can read config safely.
php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations only if the database is ready. Keep this safe for a fresh deployment.
if php artisan migrate:status --no-interaction >/dev/null 2>&1; then
  echo "-- Database migration check --"
  php artisan migrate --force --no-interaction
else
  echo "Database unavailable or not configured yet; skipping migration step."
fi

# Recreate the public storage symlink if needed.
if [[ -d public/storage ]] || [[ -L public/storage ]]; then
  echo "public/storage already exists"
else
  php artisan storage:link --force || true
fi

# Check health endpoints.
APP_URL="$(grep -E '^APP_URL=' .env 2>/dev/null | head -n 1 | cut -d= -f2- | tr -d '\r' || true)"
TARGET_URL="${APP_URL:-http://127.0.0.1}"

if [[ "$TARGET_URL" != http* ]]; then
  TARGET_URL="https://$TARGET_URL"
fi

check_url() {
  local url="$1"
  if curl -fsSL "$url" >/tmp/post_deploy_check.out 2>/dev/null; then
    echo "OK $url"
    return 0
  fi
  return 1
}

echo "-- Health endpoint check --"
if ! check_url "${TARGET_URL%/}/up"; then
  if ! check_url "${TARGET_URL%/}/health"; then
    echo "Primary health checks failed against $TARGET_URL" >&2
    echo "Check that the app is reachable and the web server is pointing at the Laravel public directory." >&2
    exit 1
  fi
fi

echo "-- Mobile API check --"
API_URL="${TARGET_URL%/}/api/v1/properties"
if ! curl -fsSL -H 'Accept: application/json' "$API_URL" | php -r '$response = json_decode(stream_get_contents(STDIN), true); exit(is_array($response) && array_key_exists("data", $response) ? 0 : 1);'; then
  echo "Mobile API check failed at $API_URL" >&2
  exit 1
fi
echo "OK $API_URL"

echo "-- Application summary --"
php artisan about --only=environment,php,cache,queue || true

echo "Post-deploy validation completed successfully."
