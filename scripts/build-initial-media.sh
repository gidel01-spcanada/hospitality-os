#!/usr/bin/env bash
set -euo pipefail

# scripts/ lives inside the Laravel app repo; photo/ and seed-assets/ are one level further up.
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
WORKSPACE_ROOT="$(cd "$PROJECT_ROOT/.." && pwd)"

SOURCE_DIR="${1:-$WORKSPACE_ROOT/photo}"
TARGET_DIR="${2:-$PROJECT_ROOT/release/initial-media/uploads/properties}"
MANIFEST_PATH="${3:-$WORKSPACE_ROOT/seed-assets/properties/manifest.json}"

cd "$PROJECT_ROOT"
php -d memory_limit=1G artisan property:sync-media --source="$SOURCE_DIR" --target="$TARGET_DIR" --manifest="$MANIFEST_PATH"
