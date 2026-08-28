param(
    [ValidateSet('sqlite', 'mysql', 'mysql-seed')]
    [string]$Mode = 'mysql',
    [string]$OutputPath,
    [switch]$SkipSeedRefresh,
    [switch]$PromptForSeedPasswords
)

$ErrorActionPreference = 'Stop'

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$AppRoot = $ProjectRoot
$DatabasePath = Join-Path $AppRoot 'database\database.sqlite'
$EnvironmentPath = Join-Path $AppRoot '.env'
if (-not $OutputPath) {
    $OutputPath = if ($Mode -eq 'mysql') {
        Join-Path $ProjectRoot 'afrik-appart-mysql-export.sql'
    } elseif ($Mode -eq 'mysql-seed') {
        Join-Path $ProjectRoot 'afrik-appart-mysql-seed-export.sql'
    } else {
        Join-Path $ProjectRoot 'afrik-appart-full-dump.sql'
    }
}
if (-not (Test-Path $AppRoot)) {
    throw "Laravel app directory not found at $AppRoot"
}

function Get-EnvironmentValue([string]$Name) {
    $value = [Environment]::GetEnvironmentVariable($Name)
    if ($null -ne $value) {
        return $value
    }

    if (-not (Test-Path $EnvironmentPath)) {
        return $null
    }

    $line = Get-Content $EnvironmentPath | Where-Object { $_ -match "^\s*$([regex]::Escape($Name))\s*=" } | Select-Object -First 1
    if ($null -eq $line) {
        return $null
    }

    $value = ($line -replace "^\s*$([regex]::Escape($Name))\s*=\s*", '').Trim()
    if ($value.Length -ge 2 -and (($value.StartsWith('"') -and $value.EndsWith('"')) -or ($value.StartsWith("'") -and $value.EndsWith("'")))) {
        return $value.Substring(1, $value.Length - 2)
    }

    return $value
}

function Confirm-SeedPasswords {
    foreach ($passwordName in @('SEED_ADMIN_PASSWORD', 'SEED_GUEST_PASSWORD')) {
        if (-not [string]::IsNullOrWhiteSpace((Get-EnvironmentValue $passwordName))) {
            continue
        }
        if (-not $PromptForSeedPasswords) {
            throw "$passwordName must be set in .env before refreshing seeds. Re-run with -PromptForSeedPasswords to enter it securely in the terminal."
        }

        $securePassword = Read-Host -Prompt $passwordName -AsSecureString
        $passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
        try {
            [Environment]::SetEnvironmentVariable($passwordName, [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer), 'Process')
        } finally {
            [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
        }
    }
}

function Invoke-SeedRefresh {
    if ($SkipSeedRefresh) {
        return
    }

    Confirm-SeedPasswords

    & php (Join-Path $AppRoot 'artisan') db:seed --force
    if ($LASTEXITCODE -ne 0) {
        throw "Laravel seed refresh failed with exit code $LASTEXITCODE. The export was not created."
    }
}

if ($Mode -eq 'mysql') {
    $connection = Get-EnvironmentValue 'DB_CONNECTION'
    if ($connection -and $connection -ne 'mysql') {
        throw "MySQL export requires DB_CONNECTION=mysql; current connection is '$connection'."
    }

    $mysqldumpCommand = Get-Command mysqldump -ErrorAction SilentlyContinue
    $mysqldumpPath = if ($mysqldumpCommand) { $mysqldumpCommand.Source } else { $null }
    if (-not $mysqldumpPath) {
        $mysqlBin = Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.0\bin\mysqldump.exe'
        if (Test-Path $mysqlBin) {
            $mysqldumpPath = $mysqlBin
        }
    }
    if (-not $mysqldumpPath) {
        throw 'mysqldump was not found. Install the MySQL client tools and ensure mysqldump is on PATH.'
    }

    Invoke-SeedRefresh

    $database = Get-EnvironmentValue 'DB_DATABASE'
    $dbHost = Get-EnvironmentValue 'DB_HOST'
    $port = Get-EnvironmentValue 'DB_PORT'
    $username = Get-EnvironmentValue 'DB_USERNAME'
    $password = Get-EnvironmentValue 'DB_PASSWORD'
    if (-not $dbHost) { $dbHost = '127.0.0.1' }
    if (-not $port) { $port = '3306' }
    if (-not $username) { $username = 'root' }
    if ($null -eq $password) { $password = '' }

    if (-not $database) {
        throw 'DB_DATABASE is required for MySQL export.'
    }

    $previousMysqlPassword = $env:MYSQL_PWD
    try {
        $env:MYSQL_PWD = $password
        & $mysqldumpPath --add-drop-table --single-transaction --routines --triggers --hex-blob --default-character-set=utf8mb4 --host=$dbHost --port=$port --user=$username $database > $OutputPath
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

if ($Mode -eq 'mysql-seed') {
    $temporaryDatabasePath = Join-Path $AppRoot 'database\mysql-seed-export.sqlite'
    $previousDatabaseConnection = [Environment]::GetEnvironmentVariable('DB_CONNECTION', 'Process')
    $previousDatabasePath = [Environment]::GetEnvironmentVariable('DB_DATABASE', 'Process')

    if ($SkipSeedRefresh) {
        if (-not (Test-Path $DatabasePath)) {
            throw "SQLite database not found at $DatabasePath. Run without -SkipSeedRefresh to create a clean seed export."
        }
        $exportDatabasePath = $DatabasePath
    } else {
        Confirm-SeedPasswords
        Remove-Item $temporaryDatabasePath -Force -ErrorAction SilentlyContinue
        New-Item -ItemType File -Path $temporaryDatabasePath -Force | Out-Null
        try {
            [Environment]::SetEnvironmentVariable('DB_CONNECTION', 'sqlite', 'Process')
            [Environment]::SetEnvironmentVariable('DB_DATABASE', $temporaryDatabasePath, 'Process')
            & php (Join-Path $AppRoot 'artisan') migrate:fresh --seed --force
            if ($LASTEXITCODE -ne 0) {
                throw "Laravel fresh migration and seed failed with exit code $LASTEXITCODE. The export was not created."
            }
            $exportDatabasePath = $temporaryDatabasePath
        } finally {
            [Environment]::SetEnvironmentVariable('DB_CONNECTION', $previousDatabaseConnection, 'Process')
            [Environment]::SetEnvironmentVariable('DB_DATABASE', $previousDatabasePath, 'Process')
        }
    }

    $mysqlSeedPythonScript = @'
import sqlite3
import sys
from pathlib import Path

db_path = Path(r"__DB_PATH__")
out_path = Path(r"__OUT_PATH__")
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
            if not columns:
                continue
            quoted_columns = ", ".join(quote_identifier(column) for column in columns)
            for row in conn.execute(f'SELECT * FROM "{table}"'):
                values = ", ".join(mysql_literal(value) for value in row)
                output.write(f"INSERT INTO {quote_identifier(table)} ({quoted_columns}) VALUES ({values});\n")
        output.write("COMMIT;\nSET FOREIGN_KEY_CHECKS = 1;\n")
finally:
    conn.close()

print(f"Exported refreshed SQLite data as MySQL INSERT statements to {out_path}")
'@

    try {
        $mysqlSeedPythonScript = $mysqlSeedPythonScript.Replace('__DB_PATH__', $exportDatabasePath.Replace('\', '\\')).Replace('__OUT_PATH__', $OutputPath.Replace('\', '\\'))
        $mysqlSeedPythonScript | python -

        if ($LASTEXITCODE -ne 0 -or -not (Test-Path $OutputPath)) {
            throw "MySQL seed export was not created at $OutputPath"
        }
    } finally {
        if (-not $SkipSeedRefresh) {
            Remove-Item $temporaryDatabasePath -Force -ErrorAction SilentlyContinue
        }
    }

    Write-Host "MySQL seed export file ready: $OutputPath"
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
