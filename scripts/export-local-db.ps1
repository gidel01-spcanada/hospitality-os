param(
    [ValidateSet('sqlite', 'mysql')]
    [string]$Mode = 'sqlite',
    [string]$OutputPath
)

$ErrorActionPreference = 'Stop'

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$AppRoot = $ProjectRoot
$DatabasePath = Join-Path $AppRoot 'database\database.sqlite'
if (-not $OutputPath) {
    $OutputPath = if ($Mode -eq 'mysql') {
        Join-Path $ProjectRoot 'afrik-appart-mysql-export.sql'
    } else {
        Join-Path $ProjectRoot 'afrik-appart-full-dump.sql'
    }
}
$RequiredTables = @(
    'migrations',
    'users',
    'properties',
    'reservations',
    'payment_attempts',
    'email_outbox',
    'settings',
    'currency_configs',
    'amenities'
)

if (-not (Test-Path $AppRoot)) {
    throw "Laravel app directory not found at $AppRoot"
}

if ($Mode -eq 'mysql') {
    $mysqldump = Get-Command mysqldump -ErrorAction SilentlyContinue
    if (-not $mysqldump) {
        throw 'mysqldump was not found. Install the MySQL client tools and ensure mysqldump is on PATH.'
    }

    $database = $env:DB_DATABASE
    $host = if ($env:DB_HOST) { $env:DB_HOST } else { '127.0.0.1' }
    $port = if ($env:DB_PORT) { $env:DB_PORT } else { '3306' }
    $username = if ($env:DB_USERNAME) { $env:DB_USERNAME } else { 'root' }
    $password = if ($null -ne $env:DB_PASSWORD) { $env:DB_PASSWORD } else { '' }

    if (-not $database) {
        throw 'DB_DATABASE is required for MySQL export.'
    }

    $previousMysqlPassword = $env:MYSQL_PWD
    try {
        $env:MYSQL_PWD = $password
        & $mysqldump.Source --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 --host=$host --port=$port --user=$username $database > $OutputPath
        if ($LASTEXITCODE -ne 0) {
            throw "mysqldump failed with exit code $LASTEXITCODE. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and credentials."
        }
    } finally {
        $env:MYSQL_PWD = $previousMysqlPassword
    }

    if (-not (Test-Path $OutputPath)) {
        throw "Export file was not created at $OutputPath"
    }

    Write-Host "MySQL export file ready: $OutputPath"
    exit 0
}

if (-not (Test-Path $DatabasePath)) {
    throw "SQLite database not found at $DatabasePath. Run php artisan migrate --seed or restore the database first."
}

$pythonScript = @'
import json
import sqlite3
import sys
from pathlib import Path

db_path = Path(r"__DB_PATH__")
out_path = Path(r"__OUT_PATH__")
required_tables = [
    "migrations",
    "users",
    "properties",
    "reservations",
    "payment_attempts",
    "email_outbox",
    "settings",
    "currency_configs",
    "amenities",
]

conn = sqlite3.connect(str(db_path))
try:
    tables = conn.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name").fetchall()
    table_names = [name[0] for name in tables]
    missing = [table for table in required_tables if table not in table_names]
    if missing:
        raise SystemExit(f"Missing required tables: {', '.join(missing)}")

    with out_path.open("w", encoding="utf-8") as f:
        f.write("BEGIN TRANSACTION;\n")
        for table in table_names:
            schema_row = conn.execute("SELECT sql FROM sqlite_master WHERE type='table' AND name=?", (table,)).fetchone()
            if schema_row and schema_row[0]:
                f.write(schema_row[0] + ';\n')

            columns = conn.execute(f'PRAGMA table_info("{table}")').fetchall()
            if not columns:
                continue

            column_names = [column[1] for column in columns]
            rows = conn.execute(f'SELECT * FROM "{table}"').fetchall()
            for row in rows:
                values = []
                for value in row:
                    if value is None:
                        values.append("NULL")
                    elif isinstance(value, (int, float)):
                        values.append(str(value))
                    else:
                        values.append("'" + str(value).replace("'", "''") + "'")
                f.write(f'INSERT INTO "{table}" ({", ".join(f"\"{name}\"" for name in column_names)}) VALUES ({", ".join(values)});\n')
        f.write("COMMIT;\n")
finally:
    conn.close()

print(f"Validated required tables and exported database dump to {out_path}")
'@

$pythonScript = $pythonScript.Replace('__DB_PATH__', $DatabasePath.Replace('\', '\\')).Replace('__OUT_PATH__', $OutputPath.Replace('\', '\\'))
$pythonScript | python -

if (-not (Test-Path $OutputPath)) {
    throw "Export file was not created at $OutputPath"
}

Write-Host "Export file ready: $OutputPath"
