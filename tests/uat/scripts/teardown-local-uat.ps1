<#
.SYNOPSIS
  Stops local UAT servers, removes test-created uploads, restores files rewritten by seeding, deletes %TEMP%\uat-run (databases and secrets).
  Only untracked files under public/uploads created today or later than -Since are deleted; older untracked files are kept.
#>
param([datetime]$Since = (Get-Date).Date)
$repo = (Resolve-Path "$PSScriptRoot\..\..\..").Path
Get-CimInstance Win32_Process -Filter "Name='php.exe'" |
  Where-Object { $_.CommandLine -match 'artisan.*serve.*--port=81\d\d' -or $_.CommandLine -match '--port=81\d\d' } |
  ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }
Start-Sleep 2
Push-Location $repo
try {
  foreach ($f in (git ls-files --others --exclude-standard -- public/uploads)) {
    $i = Get-Item $f
    if ($i.CreationTime -ge $Since) { Remove-Item $i.FullName -Force }
  }
  foreach ($dir in (Get-ChildItem public/uploads -Recurse -Directory | Sort-Object { $_.FullName.Length } -Descending)) {
    if (-not (Get-ChildItem $dir.FullName -Force) -and -not (git ls-files $dir.FullName)) { Remove-Item $dir.FullName }
  }
  git checkout -- seed-assets/properties/manifest.json public/uploads/properties 2>$null
} finally { Pop-Location }
Remove-Item (Join-Path $env:TEMP 'uat-run') -Recurse -Force -ErrorAction SilentlyContinue
git -C $repo status --short
