#!/usr/bin/env bash
set -euo pipefail

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_ROOT="$PROJECT_ROOT"
ENV_FILE="$APP_ROOT/.env"
ENV_EXAMPLE="$APP_ROOT/.env.example"

if [[ ! -f "$ENV_EXAMPLE" ]]; then
  echo "Missing $ENV_EXAMPLE" >&2
  exit 1
fi

if [[ ! -f "$ENV_FILE" ]]; then
  cp "$ENV_EXAMPLE" "$ENV_FILE"
fi

if ! command -v php >/dev/null 2>&1; then
  echo "php is required but not installed" >&2
  exit 1
fi

if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required but not installed" >&2
  exit 1
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "npm is required but not installed" >&2
  exit 1
fi

cd "$APP_ROOT"
composer install --no-interaction --prefer-dist
npm install
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force
printf '\nDevelopment setup complete. Next: php artisan serve\n'
