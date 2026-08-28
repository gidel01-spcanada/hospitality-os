param(
    [string]$InputPath = (Join-Path (Split-Path $PSScriptRoot -Parent) 'afrik-appart-mysql-seed-export.sql')
)

$ErrorActionPreference = 'Stop'

$AppRoot = Split-Path $PSScriptRoot -Parent
$EnvironmentPath = Join-Path $AppRoot '.env'

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

if (-not (Test-Path $InputPath)) {
    throw "MySQL seed export not found at $InputPath"
}

$connection = Get-EnvironmentValue 'DB_CONNECTION'
if ($connection -ne 'mysql') {
    throw "MySQL import requires DB_CONNECTION=mysql; current connection is '$connection'."
}

$database = Get-EnvironmentValue 'DB_DATABASE'
$dbHost = Get-EnvironmentValue 'DB_HOST'
$port = Get-EnvironmentValue 'DB_PORT'
$username = Get-EnvironmentValue 'DB_USERNAME'
$password = Get-EnvironmentValue 'DB_PASSWORD'
if (-not $database) { throw 'DB_DATABASE is required for MySQL import.' }
if (-not $dbHost) { $dbHost = '127.0.0.1' }
if (-not $port) { $port = '3306' }
if (-not $username) { $username = 'root' }
if ($null -eq $password) { $password = '' }

$mysqlCommand = Get-Command mysql -ErrorAction SilentlyContinue
$mysqlPath = if ($mysqlCommand) { $mysqlCommand.Source } else { $null }
if (-not $mysqlPath) {
    $mysqlBin = Join-Path $env:ProgramFiles 'MySQL\MySQL Server 8.0\bin\mysql.exe'
    if (Test-Path $mysqlBin) {
        $mysqlPath = $mysqlBin
    }
}
if (-not $mysqlPath) {
    throw 'mysql was not found. Install the MySQL client tools and ensure mysql is on PATH.'
}

& php (Join-Path $AppRoot 'artisan') migrate --force
if ($LASTEXITCODE -ne 0) {
    throw "Laravel migration failed with exit code $LASTEXITCODE. The seed export was not imported."
}

$previousMysqlPassword = $env:MYSQL_PWD
try {
    $env:MYSQL_PWD = $password
    Get-Content -Raw $InputPath | & $mysqlPath --default-character-set=utf8mb4 --host=$dbHost --port=$port --user=$username $database
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL seed import failed with exit code $LASTEXITCODE."
    }
} finally {
    $env:MYSQL_PWD = $previousMysqlPassword
}

Write-Host "MySQL migrations and seed import completed for database '$database'."