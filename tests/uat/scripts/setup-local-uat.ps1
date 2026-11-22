<#
.SYNOPSIS
  Builds isolated local UAT instances (throwaway SQLite DBs seeded with the repo seeders) and starts one php server per instance.

.DESCRIPTION
  Instance N uses http://127.0.0.1:810N and %TEMP%\uat-run\dbN.sqlite. Random seed passwords and a webhook secret
  are generated into %TEMP%\uat-run\secrets.txt (outside the repository). They are never printed.
  After setup, dot-source nothing: run  & "$env:TEMP\uat-run\env.ps1" -N <n>  in a shell before any `php artisan` command for instance n.
  Seeding rewrites two tracked files (media sync); this script restores them with git checkout.
  Run teardown-local-uat.ps1 when finished.

.PARAMETER Instances
  Number of instances (default 5).
.PARAMETER PhpExtDir
  PHP ext directory. Default: <php dir>\ext. Needed because some Windows PHP installs have no php.ini loading mbstring/sqlite.
#>
param(
  [int]$Instances = 5,
  [string]$PhpExtDir
)
$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path "$PSScriptRoot\..\..\..").Path
$d = Join-Path $env:TEMP 'uat-run'
New-Item -ItemType Directory -Force $d | Out-Null

if (-not $PhpExtDir) { $PhpExtDir = Join-Path (Split-Path (& php -r 'echo PHP_BINARY;')) 'ext' }
$ext = 'mbstring','openssl','pdo_sqlite','sqlite3','fileinfo','curl','gd','intl','exif','zip','sodium'
$ini = "extension_dir=`"$PhpExtDir`"`n" + (($ext | ForEach-Object { "extension=$_" }) -join "`n") + "`nmemory_limit=512M`n"
Set-Content (Join-Path $d 'php.ini') $ini

function New-Secret($suffix) { (-join ((48..57) + (65..90) + (97..122) | Get-Random -Count 22 | ForEach-Object { [char]$_ })) + $suffix }
$secretsFile = Join-Path $d 'secrets.txt'
if (-not (Test-Path $secretsFile)) {
  Set-Content $secretsFile @("ADMIN=$(New-Secret '!a1')", "GUEST=$(New-Secret '!g1')", "WEBHOOK=$(New-Secret '')")
}

@'
param([int]$N)
# Sets process env for local UAT instance N. Never print the values set here.
$d = "$env:TEMP\uat-run"
$env:PHPRC = $d
$s = @{}; Get-Content "$d\secrets.txt" | ForEach-Object { $k,$v = $_ -split '=',2; $s[$k]=$v }
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE="$d\db$N.sqlite"
$env:SEED_ADMIN_PASSWORD=$s['ADMIN']; $env:SEED_GUEST_PASSWORD=$s['GUEST']; $env:PAYMENT_WEBHOOK_SECRET=$s['WEBHOOK']
$env:APP_URL="http://127.0.0.1:$(8100+$N)"; $env:MAIL_MAILER='log'; $env:APP_ENV='local'; $env:APP_DEBUG='false'; $env:RECAPTCHA_ENABLED='false'
$global:ProgressPreference='SilentlyContinue'
'@ | Set-Content (Join-Path $d 'env.ps1')

Push-Location $repo
try {
  # Seed instance 1 once, then clone the file.
  $db1 = Join-Path $d 'db1.sqlite'
  Remove-Item $db1 -ErrorAction SilentlyContinue
  New-Item $db1 -ItemType File | Out-Null
  & (Join-Path $d 'env.ps1') -N 1
  php artisan migrate --force | Out-Null
  php artisan db:seed --force | Out-Null
  foreach ($n in 2..$Instances) { Copy-Item $db1 (Join-Path $d "db$n.sqlite") -Force }

  foreach ($n in 1..$Instances) {
    & (Join-Path $d 'env.ps1') -N $n
    Start-Process php -ArgumentList 'artisan','serve','--host=127.0.0.1',"--port=$(8100+$n)" -WindowStyle Hidden `
      -RedirectStandardOutput (Join-Path $d "srv$n.out") -RedirectStandardError (Join-Path $d "srv$n.err")
  }
  Start-Sleep 6
  $ProgressPreference = 'SilentlyContinue'
  foreach ($n in 1..$Instances) {
    $code = try { (Invoke-WebRequest "http://127.0.0.1:$(8100+$n)/" -UseBasicParsing -TimeoutSec 60).StatusCode } catch { 'ERR' }
    "instance $n http://127.0.0.1:$(8100+$n) -> $code"
  }
  # Seeding syncs property media into tracked files; restore them.
  git checkout -- seed-assets/properties/manifest.json public/uploads/properties 2>$null
} finally { Pop-Location }
"Done. Before artisan commands: & `"$d\env.ps1`" -N <n>"
