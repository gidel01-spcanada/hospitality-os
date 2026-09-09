$ErrorActionPreference = 'Stop'

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$ProjectRoot = Split-Path $PSScriptRoot -Parent
$AppRoot = $ProjectRoot
$ReleaseRoot = Join-Path $ProjectRoot 'release'
$AppRelease = Join-Path $ReleaseRoot 'app'
$PublicRelease = Join-Path $ReleaseRoot 'public'
$ManifestPath = Join-Path $ReleaseRoot 'release-manifest.sha256'

if (-not (Test-Path $AppRoot)) {
    throw "Laravel app directory not found at $AppRoot"
}

if (-not (Test-Path (Join-Path $AppRoot 'composer.json'))) {
    throw "composer.json not found in $AppRoot"
}

if (-not (Get-Command composer -ErrorAction SilentlyContinue)) {
    throw 'composer is required but not installed.'
}

if (-not (Get-Command npm -ErrorAction SilentlyContinue)) {
    throw 'npm is required but not installed.'
}

New-Item -ItemType Directory -Force -Path $AppRelease, $PublicRelease | Out-Null

# $ErrorActionPreference does not apply to native executables, so exit codes must be checked explicitly.
function Invoke-BuildStep {
    param(
        [Parameter(Mandatory)] [string] $Description,
        [Parameter(Mandatory)] [scriptblock] $Action
    )

    & $Action
    if ($LASTEXITCODE -ne 0) {
        throw "$Description failed with exit code $LASTEXITCODE."
    }
}

Push-Location $AppRoot
try {
    Invoke-BuildStep 'composer install' { composer install --no-interaction --prefer-dist }

    # npm ci wipes node_modules, which fails on Windows while a native module is still locked.
    npm ci
    if ($LASTEXITCODE -ne 0) {
        Write-Warning 'npm ci failed (likely a locked native module); falling back to npm install.'
        Invoke-BuildStep 'npm install' { npm install --no-audit --no-fund }
    }

    # app.css declares @source on storage/framework/views, so compile Blade first for a deterministic bundle.
    Invoke-BuildStep 'php artisan view:cache' { php artisan view:cache }
    Invoke-BuildStep 'npm run build' { npm run build }

    $viteManifest = Join-Path $AppRoot 'public\build\manifest.json'
    if (-not (Test-Path $viteManifest)) {
        throw "Vite build did not produce $viteManifest; refusing to package a release without assets."
    }

    Invoke-BuildStep 'Laravel config clear before test suite' { php artisan config:clear }
    # The media import test processes 58 source images and is validated separately in local development.
    Invoke-BuildStep 'Laravel test suite (excluding long media import)' { php artisan test --exclude-filter=PropertyMediaImportTest }

    git clean -fd -- public/uploads
    Invoke-BuildStep 'composer install (production)' { composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader }
}
finally {
    Pop-Location
}

Remove-Item $AppRelease, $PublicRelease -Recurse -Force -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force -Path $AppRelease, $PublicRelease | Out-Null

$rootItemsToSkip = @(
    '.bluehost-credentials.json',
    '.bluehost-credentials.template.json',
    '.git',
    '.github',
    'afrikappart_db.txt',
    'amenities-before.png',
    'bluehost-deploy-script.ps1',
    'details_preview.html',
    'home_preview.html',
    'node_modules',
    'photo',
    'project-state.json',
    'release',
    'seed-assets',
    'short-term-rental-website-master-prompt (1).md',
    'short-term-rental-website-master-prompt (2).md',
    'short-term-rental-website-master-prompt.md',
    'tests'
)
Get-ChildItem $AppRoot -Force |
    Where-Object { $_.Name -notin $rootItemsToSkip } |
    ForEach-Object { Copy-Item $_.FullName $AppRelease -Recurse -Force }

$pathsToRemove = @(
    'tests',
    'phpunit.xml',
    '.env',
    '.env.example',
    '.phpunit.result.cache',
    'bootstrap\cache\config.php',
    'database\*.sqlite',
    'database\*.sqlite-*',
    'storage\logs',
    'storage\framework\cache',
    'storage\framework\sessions',
    'storage\framework\testing',
    'public\build',
    'public\hot'
)
foreach ($path in $pathsToRemove) {
    $target = Join-Path $AppRelease $path
    if (Test-Path $target) {
        Remove-Item $target -Recurse -Force
    }
}

Copy-Item (Join-Path $AppRoot 'public\*') $PublicRelease -Recurse -Force
Remove-Item (Join-Path $PublicRelease 'hot') -Force -ErrorAction SilentlyContinue

# Rewrite the manifest from scratch; appending would carry stale hashes from previous builds.
Remove-Item $ManifestPath -Force -ErrorAction SilentlyContinue

$files = Get-ChildItem $ReleaseRoot -Recurse -File | Where-Object { $_.FullName -ne $ManifestPath }
if ($files.Count -gt 0) {
    $files |
        ForEach-Object { (Get-FileHash -Path $_.FullName -Algorithm SHA256).Hash + '  ' + $_.FullName } |
        Set-Content -Encoding utf8 -Path $ManifestPath
}

Push-Location $AppRoot
try {
    Invoke-BuildStep 'composer install (development restore)' { composer install --no-interaction --prefer-dist }
    Invoke-BuildStep 'Laravel config cache (development restore)' { php artisan config:cache }
}
finally {
    Pop-Location
}

Write-Host "Release package created successfully."
Write-Host "App bundle: $AppRelease"
Write-Host "Public bundle: $PublicRelease"
Write-Host "Checksum manifest: $ManifestPath"
