param(
    [string]$SourceDir = 'C:\Users\gfoumbi\Downloads\Afrik Appart\photo',
    [string]$TargetDir = 'C:\Users\gfoumbi\Downloads\Afrik Appart\repository\release\initial-media\uploads\properties',
    [string]$ManifestPath = 'C:\Users\gfoumbi\Downloads\Afrik Appart\seed-assets\properties\manifest.json'
)

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $projectRoot

php -d memory_limit=1G artisan property:sync-media --source="$SourceDir" --target="$TargetDir" --manifest="$ManifestPath"
