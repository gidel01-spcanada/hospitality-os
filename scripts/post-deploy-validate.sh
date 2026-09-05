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

if [[ ! -f public/build/manifest.json ]]; then
  echo "Vite manifest is missing at $APP_ROOT/public/build/manifest.json." >&2
  echo "Deploy release/public/build to both the Laravel app public/build directory and the web document root build directory." >&2
  exit 1
fi

# Ensure the app reads the current .env before checking database state.
php artisan config:clear >/dev/null 2>&1 || true
php artisan cache:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true
php artisan view:clear >/dev/null 2>&1 || true

env_value() {
  local key="$1"
  grep -E "^${key}=" .env 2>/dev/null | head -n 1 | cut -d= -f2- | tr -d '\r' | sed -E 's/^"(.*)"$/\1/' || true
}

echo "-- Database config check --"
DB_CONNECTION_VALUE="$(env_value DB_CONNECTION)"
DB_HOST_VALUE="$(env_value DB_HOST)"
DB_DATABASE_VALUE="$(env_value DB_DATABASE)"
DB_USERNAME_VALUE="$(env_value DB_USERNAME)"
echo "DB_CONNECTION=${DB_CONNECTION_VALUE:-unset}"
if [[ "${DB_CONNECTION_VALUE:-}" == "mysql" || "${DB_CONNECTION_VALUE:-}" == "mariadb" ]]; then
  echo "DB_HOST=${DB_HOST_VALUE:-unset}"
  echo "DB_DATABASE=${DB_DATABASE_VALUE:-unset}"
  echo "DB_USERNAME=${DB_USERNAME_VALUE:-unset}"
fi

detect_mysql_schema_state() {
  php artisan tinker --execute='
try {
    $tables = collect(DB::select("SHOW TABLES"))->map(fn ($row) => array_values((array) $row)[0]);
    echo "migrations=" . ($tables->contains("migrations") ? "yes" : "no") . PHP_EOL;
    echo "table_count=" . $tables->count() . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}
'
}

if [[ "${DB_CONNECTION_VALUE:-}" == "mysql" || "${DB_CONNECTION_VALUE:-}" == "mariadb" ]]; then
  if MYSQL_SCHEMA_STATE="$(detect_mysql_schema_state 2>/tmp/post_deploy_schema_state.err)"; then
    echo "$MYSQL_SCHEMA_STATE"
    MIGRATIONS_TABLE="$(printf '%s\n' "$MYSQL_SCHEMA_STATE" | awk -F= '/^migrations=/{print $2}')"
    TABLE_COUNT="$(printf '%s\n' "$MYSQL_SCHEMA_STATE" | awk -F= '/^table_count=/{print $2}')"
    if [[ "$MIGRATIONS_TABLE" == "no" && "${TABLE_COUNT:-0}" -gt 0 ]]; then
      echo "Database has existing tables but no Laravel migrations table." >&2
      echo "Do not run php artisan migrate yet: Laravel would try to recreate existing tables." >&2
      echo "Choose one recovery path: empty the database and run migrate, or create a migration baseline for the existing schema." >&2
      exit 1
    fi
  else
    echo "Unable to inspect database schema state." >&2
    if [[ -s /tmp/post_deploy_schema_state.err ]]; then
      sed -n '1,8p' /tmp/post_deploy_schema_state.err >&2
    fi
  fi
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations only if the database is ready. Keep this safe for a fresh deployment.
if php artisan migrate:status --no-interaction >/tmp/post_deploy_migrate_status.out 2>/tmp/post_deploy_migrate_status.err; then
  echo "-- Database migration check --"
  php artisan migrate --force --no-interaction
else
  echo "Database unavailable or not configured yet; skipping migration step."
  if [[ -s /tmp/post_deploy_migrate_status.err ]]; then
    sed -n '1,8p' /tmp/post_deploy_migrate_status.err >&2
  fi
  if [[ -s /tmp/post_deploy_migrate_status.out ]]; then
    sed -n '1,8p' /tmp/post_deploy_migrate_status.out >&2
  fi
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
  echo "GET $url"
  local status
  status="$(curl -k -L -sS -o /tmp/post_deploy_check.out -w '%{http_code}' "$url" 2>/tmp/post_deploy_check.err || true)"
  if [[ "$status" =~ ^[23][0-9][0-9]$ ]]; then
    echo "OK $url"
    return 0
  fi
  echo "FAILED $url status=${status:-curl-error}" >&2
  if [[ -s /tmp/post_deploy_check.err ]]; then
    sed -n '1,5p' /tmp/post_deploy_check.err >&2
  fi
  if [[ -s /tmp/post_deploy_check.out ]]; then
    sed -n '1,8p' /tmp/post_deploy_check.out >&2
  fi
  return 1
}

echo "-- Health endpoint check --"
if ! check_url "${TARGET_URL%/}/up"; then
  if ! check_url "${TARGET_URL%/}/health"; then
    echo "Primary health checks failed against $TARGET_URL" >&2
    echo "Check that the app is reachable and the web server is pointing at the Laravel public directory." >&2
    if [[ -f storage/logs/laravel.log ]]; then
      echo "-- Last Laravel log lines --" >&2
      tail -n 80 storage/logs/laravel.log >&2 || true
    else
      echo "No Laravel log found at storage/logs/laravel.log" >&2
    fi
    exit 1
  fi
fi

echo "-- Asset mode check --"
if curl -k -L -sS "${TARGET_URL%/}/" >/tmp/post_deploy_home.out 2>/tmp/post_deploy_home.err; then
  if grep -Eq '(@vite/client|\[::1\]:5173|localhost:5173|127\.0\.0\.1:5173|/resources/css/|/resources/js/)' /tmp/post_deploy_home.out; then
    echo "Production page is still referencing Vite dev-server assets." >&2
    echo "Remove hot files from both public roots, then recache views:" >&2
    echo "  rm -f public/hot ../public_html/hot" >&2
    echo "  php artisan view:clear && php artisan view:cache" >&2
    exit 1
  fi
else
  echo "Unable to fetch ${TARGET_URL%/}/ for asset mode check." >&2
  if [[ -s /tmp/post_deploy_home.err ]]; then
    sed -n '1,5p' /tmp/post_deploy_home.err >&2
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
