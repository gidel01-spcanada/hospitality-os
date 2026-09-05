param(
    [int]$Port = 8000
)

$repositoryRoot = Split-Path -Parent $PSScriptRoot
$router = Join-Path $repositoryRoot 'vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php'
$publicPath = Join-Path $repositoryRoot 'public'

Set-Location $publicPath
php -d upload_max_filesize=3M -d post_max_size=4M -S "127.0.0.1:$Port" $router