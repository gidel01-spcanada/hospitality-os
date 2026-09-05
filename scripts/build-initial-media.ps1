param(
     [string]$SourceDir = 'C:\Users\gidel\source\repos\hospitality-os\photo',
    [string]$TargetDir = 'C:\Users\gidel\source\repos\hospitality-os\release\initial-media\uploads\properties',
    [string]$ManifestPath = 'C:\Users\gidel\source\repos\hospitality-os\seed-assets\properties\manifest.json'
)

# scripts/ lives inside the Laravel app repo, so the app root is the repo root itself.
$projectRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Root

php -d memory_limit=1G artisan property:sync-media --source="$SourceDir" --target="$TargetDir" --manifest="$ManifestPath"
