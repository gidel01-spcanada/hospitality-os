#!/usr/bin/env bash
set -euo pipefail

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
APP_ROOT="$PROJECT_ROOT"
RELEASE_ROOT="$PROJECT_ROOT/release"
APP_RELEASE="$RELEASE_ROOT/app"
PUBLIC_RELEASE="$RELEASE_ROOT/public"
MANIFEST_PATH="$RELEASE_ROOT/release-manifest.sha256"

if [[ ! -d "$APP_ROOT" ]]; then
  echo "Laravel app directory not found at $APP_ROOT" >&2
  exit 1
fi

if [[ ! -f "$APP_ROOT/composer.json" ]]; then
  echo "composer.json not found in $APP_ROOT" >&2
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

mkdir -p "$APP_RELEASE" "$PUBLIC_RELEASE"

cd "$APP_ROOT"
composer install --no-interaction --prefer-dist
npm ci
# app.css declares @source on storage/framework/views, so compile Blade first for a deterministic bundle.
php artisan view:cache
npm run build

if [[ ! -f "$APP_ROOT/public/build/manifest.json" ]]; then
  echo "Vite build did not produce public/build/manifest.json; refusing to package a release without assets." >&2
  exit 1
fi

php artisan test
git clean -fd -- public/uploads
composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader

rm -rf "$APP_RELEASE" "$PUBLIC_RELEASE"
mkdir -p "$APP_RELEASE" "$PUBLIC_RELEASE"

cp -a "$APP_ROOT"/. "$APP_RELEASE"/
rm -rf "$APP_RELEASE/.git" "$APP_RELEASE/.github" "$APP_RELEASE/node_modules" "$APP_RELEASE/tests" "$APP_RELEASE/phpunit.xml" "$APP_RELEASE/.env" "$APP_RELEASE/.env.example" "$APP_RELEASE/.phpunit.result.cache" "$APP_RELEASE/database/*.sqlite" "$APP_RELEASE/database/*.sqlite-*" "$APP_RELEASE/storage/logs" "$APP_RELEASE/storage/framework/cache" "$APP_RELEASE/storage/framework/sessions" "$APP_RELEASE/storage/framework/testing" "$APP_RELEASE/public/build" "$APP_RELEASE/vendor/bin" 2>/dev/null || true

cp -a "$APP_ROOT/public"/. "$PUBLIC_RELEASE"/

# Rewrite the manifest from scratch; appending would carry stale hashes from previous builds.
: > "$MANIFEST_PATH"

find "$RELEASE_ROOT" -type f \( -not -path "$MANIFEST_PATH" \) -print0 | while IFS= read -r -d '' file; do
  sha256sum "$file" >> "$MANIFEST_PATH"
done

cat <<EOF
Release package created successfully.
App bundle: $APP_RELEASE
Public bundle: $PUBLIC_RELEASE
Checksum manifest: $MANIFEST_PATH
EOF
