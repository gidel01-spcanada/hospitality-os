$ErrorActionPreference = 'Stop'

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$AppRoot = $ProjectRoot
$EnvFile = Join-Path $AppRoot '.env'
$EnvExample = Join-Path $AppRoot '.env.example'

if (-not (Test-Path $EnvExample)) {
    throw "Missing $EnvExample"
}

if (-not (Test-Path $EnvFile)) {
    Copy-Item $EnvExample $EnvFile
}

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    throw 'php is required but not installed.'
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'composer is required but not installed.'
}

if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'npm is required but not installed.'
}

Push-Location $AppRoot
try {
    composer install --no-interaction --prefer-dist
    npm install
    php artisan key:generate --force
    php artisan migrate --force
    php artisan db:seed --force
}
finally {
    Pop-Location
}

Write-Host 'Development setup complete. Next: php artisan serve'
