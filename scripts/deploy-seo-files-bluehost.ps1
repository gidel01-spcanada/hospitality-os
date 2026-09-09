param(
    # Credentials stay one level above the repo so they are never committed.
    [string]$CredentialsPath = (Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) '.bluehost-credentials.json'),
    [string]$ReleaseRoot = (Join-Path (Split-Path $PSScriptRoot -Parent) 'release')
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $CredentialsPath -PathType Leaf)) {
    throw "Credentials file not found: $CredentialsPath"
}

$config = Get-Content $CredentialsPath -Raw | ConvertFrom-Json
foreach ($property in @('host', 'username', 'password', 'publicPath')) {
    if (-not $config.$property) {
        throw "Missing '$property' in the credentials file."
    }
}
if ($config.password -like 'REPLACE_*') {
    throw 'The credentials file still contains the placeholder password.'
}

$seoFiles = @('robots.txt', 'sitemap.xml')
$publicRelease = Join-Path $ReleaseRoot 'public'
foreach ($seoFile in $seoFiles) {
    if (-not (Test-Path (Join-Path $publicRelease $seoFile) -PathType Leaf)) {
        throw "Required SEO file is missing from the release: $seoFile"
    }
}

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
if ([bool]$config.allowInvalidCertificate) {
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
    Write-Warning 'TLS certificate validation is disabled because allowInvalidCertificate is enabled.'
}

function Get-FtpUri([string]$remotePath) {
    $normalized = $remotePath.Trim('/').Replace('\', '/')
    return "ftp://$($config.host)/$normalized"
}

function Send-FtpFile([string]$localPath, [string]$remotePath) {
    $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri $remotePath))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UseBinary = $true
    $request.UsePassive = $true

    $file = $null
    $stream = $null
    $response = $null
    try {
        $file = [System.IO.File]::OpenRead($localPath)
        $stream = $request.GetRequestStream()
        $file.CopyTo($stream)
        $stream.Close()
        $stream = $null
        $response = $request.GetResponse()
        $response.Close()
    } finally {
        if ($response) { $response.Close() }
        if ($stream) { $stream.Close() }
        if ($file) { $file.Close() }
    }
}

foreach ($seoFile in $seoFiles) {
    $localPath = Join-Path $publicRelease $seoFile
    Send-FtpFile $localPath "$($config.publicPath)/$seoFile"
    Write-Host "Uploaded $seoFile"
}

Write-Host 'Bluehost SEO files deployment completed.'