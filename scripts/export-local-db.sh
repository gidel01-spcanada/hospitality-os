#!/usr/bin/env bash
set -euo pipefail

MODE="mysql"
OUTPUT_PATH=""
SKIP_SEED_REFRESH="false"
if [[ "${1:-}" == "--mysql" ]]; then
    MODE="mysql"
    shift
fi
if [[ "${1:-}" == "--mysql-seed" ]]; then
    MODE="mysql-seed"
    shift
fi
if [[ "${1:-}" == "--skip-seed-refresh" ]]; then
    SKIP_SEED_REFRESH="true"
    shift
fi
if [[ "${1:-}" == "--output" ]]; then
    OUTPUT_PATH="${2:?--output requires a path}"
    shift 2
fi
if [[ $# -ne 0 ]]; then
    echo "Usage: $0 [--mysql|--mysql-seed] [--skip-seed-refresh] [--output PATH]" >&2
    exit 1
fi

PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
APP_ROOT="$PROJECT_ROOT"
DB_PATH="$APP_ROOT/database/database.sqlite"
if [[ -z "$OUTPUT_PATH" ]]; then
    if [[ "$MODE" == "mysql" ]]; then
        OUTPUT_PATH="$PROJECT_ROOT/afrik-appart-mysql-export.sql"
    elif [[ "$MODE" == "mysql-seed" ]]; then
        OUTPUT_PATH="$PROJECT_ROOT/afrik-appart-mysql-seed-export.sql"
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

environment_value() {
    local name="$1"
    local value

    if [[ -v "$name" ]]; then
        printf '%s' "${!name}"
        return
    fi

    [[ -f "$APP_ROOT/.env" ]] || return
    value="$(sed -n -E "s/^[[:space:]]*${name}[[:space:]]*=[[:space:]]*//p" "$APP_ROOT/.env" | head -n 1)"
    value="${value#\"}"
    value="${value%\"}"
    value="${value#\'}"
    value="${value%\'}"
    printf '%s' "$value"
}

if [[ "$MODE" == "mysql" ]]; then
    DB_CONNECTION="$(environment_value DB_CONNECTION)"
    if [[ "$DB_CONNECTION" != "" && "$DB_CONNECTION" != "mysql" ]]; then
        echo "MySQL export requires DB_CONNECTION=mysql; current connection is '$DB_CONNECTION'." >&2
        exit 1
    fi
    if [[ "$SKIP_SEED_REFRESH" != "true" ]]; then
        php "$APP_ROOT/artisan" db:seed --force
    fi
    command -v mysqldump >/dev/null 2>&1 || { echo "mysqldump was not found. Install the MySQL client tools and ensure mysqldump is on PATH." >&2; exit 1; }
    DB_DATABASE="$(environment_value DB_DATABASE)"
    DB_HOST="$(environment_value DB_HOST)"
    DB_PORT="$(environment_value DB_PORT)"
    DB_USERNAME="$(environment_value DB_USERNAME)"
    DB_PASSWORD="$(environment_value DB_PASSWORD)"
    : "${DB_DATABASE:?DB_DATABASE is required for MySQL export}"
    DB_HOST="${DB_HOST:-127.0.0.1}"
    DB_PORT="${DB_PORT:-3306}"
    DB_USERNAME="${DB_USERNAME:-root}"
    MYSQL_PWD="$DB_PASSWORD" mysqldump --add-drop-table --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 \
        --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" "$DB_DATABASE" > "$OUTPUT_PATH"
    echo "MySQL export file ready: $OUTPUT_PATH"
    exit 0
fi

if [[ ! -f "$DB_PATH" ]]; then
  echo "SQLite database not found at $DB_PATH. Run php artisan migrate --seed or restore the database first." >&2
  exit 1
fi

if [[ "$MODE" == "mysql-seed" ]]; then
    if [[ "$SKIP_SEED_REFRESH" != "true" ]]; then
        php "$APP_ROOT/artisan" db:seed --force
    fi

    python3 - "$DB_PATH" "$OUTPUT_PATH" <<'PY'
import sqlite3
import sys
from pathlib import Path

db_path = Path(sys.argv[1])
out_path = Path(sys.argv[2])
seed_tables = [
    "tenants",
    "amenity_categories",
    "amenities",
    "settings",
    "currency_configs",
    "establishments",
    "properties",
    "property_amenities",
    "property_images",
    "users",
]

def mysql_literal(value):
    if value is None:
        return "NULL"
    if isinstance(value, bytes):
        return "X'" + value.hex() + "'"
    if isinstance(value, (int, float)):
        return str(value)
    return "'" + str(value).replace("\\", "\\\\").replace("'", "''") + "'"

def quote_identifier(name):
    return "`" + name.replace("`", "``") + "`"

conn = sqlite3.connect(str(db_path))
try:
    available_tables = {
        row[0]
        for row in conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")
    }
    missing_tables = [table for table in seed_tables if table not in available_tables]
    if missing_tables:
        raise SystemExit("Missing seeded tables: " + ", ".join(missing_tables))

    with out_path.open("w", encoding="utf-8", newline="\n") as output:
        output.write("-- Afrik Appart MySQL data export generated from the refreshed local seed database.\n")
        output.write("-- Import after running the Laravel migrations on an empty MySQL database.\n")
        output.write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSTART TRANSACTION;\n")
        for table in seed_tables:
            columns = [column[1] for column in conn.execute(f'PRAGMA table_info("{table}")')]
            quoted_columns = ", ".join(quote_identifier(column) for column in columns)
            for row in conn.execute(f'SELECT * FROM "{table}"'):
                values = ", ".join(mysql_literal(value) for value in row)
                output.write(f"INSERT INTO {quote_identifier(table)} ({quoted_columns}) VALUES ({values});\n")
        output.write("COMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n")
finally:
    conn.close()

print(f"Exported refreshed SQLite data as MySQL INSERT statements to {out_path}")
PY

    echo "MySQL seed export file ready: $OUTPUT_PATH"
    exit 0
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
