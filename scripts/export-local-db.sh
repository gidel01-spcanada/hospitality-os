#!/usr/bin/env bash
set -euo pipefail

MODE="sqlite"
OUTPUT_PATH=""
if [[ "${1:-}" == "--mysql" ]]; then
    MODE="mysql"
    shift
fi
if [[ "${1:-}" == "--output" ]]; then
    OUTPUT_PATH="${2:?--output requires a path}"
    shift 2
fi
if [[ $# -ne 0 ]]; then
    echo "Usage: $0 [--mysql] [--output PATH]" >&2
    exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
APP_ROOT="$PROJECT_ROOT"
DB_PATH="$APP_ROOT/database/database.sqlite"
if [[ -z "$OUTPUT_PATH" ]]; then
    if [[ "$MODE" == "mysql" ]]; then
        OUTPUT_PATH="$PROJECT_ROOT/afrik-appart-mysql-export.sql"
    else
        OUTPUT_PATH="$PROJECT_ROOT/afrik-appart-full-dump.sql"
    fi
fi
REQUIRED_TABLES=(
  migrations
  users
  properties
  reservations
  payment_attempts
  email_outbox
  settings
  currency_configs
  amenities
)

if [[ ! -d "$APP_ROOT" ]]; then
  echo "Laravel app directory not found at $APP_ROOT" >&2
  exit 1
fi

if [[ "$MODE" == "mysql" ]]; then
    command -v mysqldump >/dev/null 2>&1 || { echo "mysqldump was not found. Install the MySQL client tools and ensure mysqldump is on PATH." >&2; exit 1; }
    : "${DB_DATABASE:?DB_DATABASE is required for MySQL export}"
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    DB_USERNAME="${DB_USERNAME:-root}"
    DB_PASSWORD="${DB_PASSWORD:-}"
    MYSQL_PWD="$DB_PASSWORD" mysqldump --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 \
        --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" "$DB_DATABASE" > "$OUTPUT_PATH"
    echo "MySQL export file ready: $OUTPUT_PATH"
    exit 0
fi

if [[ ! -f "$DB_PATH" ]]; then
  echo "SQLite database not found at $DB_PATH. Run php artisan migrate --seed or restore the database first." >&2
  exit 1
fi

python3 - "$DB_PATH" "$OUTPUT_PATH" "${REQUIRED_TABLES[@]}" <<'PY'
import sqlite3
import sys
from pathlib import Path

db_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
required_tables = sys.argv[3:]

conn = sqlite3.connect(str(db_path))
try:
    table_names = [
        row[0]
        for row in conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
    ]
    missing = [table for table in required_tables if table not in table_names]
    if missing:
        raise SystemExit(f"Missing required tables: {', '.join(missing)}")

    with out_path.open("w", encoding="utf-8") as f:
        f.write("BEGIN TRANSACTION;\n")
        for table in table_names:
            schema = conn.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (table,)).fetchone()
            if schema and schema[0]:
                f.write(schema[0] + ';\n')

            columns = conn.execute(f'PRAGMA table_info("{table}")').fetchall()
            if not columns:
                continue

            column_names = [column[1] for column in columns]
            rows = conn.execute(f'SELECT * FROM "{table}"').fetchall()
            for row in rows:
                values = []
                for value in row:
                    if value is None:
                        values.append('NULL')
                    elif isinstance(value, (int, float)):
                        values.append(str(value))
                    else:
                        values.append("'" + str(value).replace("'", "''") + "'")
                f.write(f'INSERT INTO "{table}" ({", ".join(f"\"{name}\"" for name in column_names)}) VALUES ({", ".join(values)});\n')
        f.write("COMMIT;\n")
finally:
    conn.close()

print(f"Validated required tables and exported database dump to {out_path}")
PY

if [[ ! -f "$OUTPUT_PATH" ]]; then
  echo "Export file was not created at $OUTPUT_PATH" >&2
  exit 1
fi

echo "Export file ready: $OUTPUT_PATH"
