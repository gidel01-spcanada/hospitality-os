param(
    [string]$CredentialsPath = (Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) '.bluehost-credentials.json')
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path $PSScriptRoot -Parent
$PhotoRoot = Join-Path $ProjectRoot 'photo'
$ManifestPath = Join-Path $ProjectRoot 'seed-assets\properties\manifest.json'

if (-not (Test-Path $CredentialsPath)) {
    throw "Credentials file not found: $CredentialsPath"
}
if (-not (Test-Path $PhotoRoot -PathType Container)) {
    throw "Photo source directory not found: $PhotoRoot"
}
if (-not (Test-Path $ManifestPath -PathType Leaf)) {
    throw "Media manifest not found: $ManifestPath"
}

$config = Get-Content $CredentialsPath -Raw | ConvertFrom-Json
foreach ($property in @('host', 'username', 'password', 'appPath')) {
    if (-not $config.$property) {
        throw "Missing '$property' in the credentials file."
    }
}

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
if ([bool]$config.allowInvalidCertificate) {
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
    Write-Warning 'TLS certificate validation is disabled because allowInvalidCertificate is enabled.'
}

function ConvertTo-FtpUri([string]$remotePath) {
    $encodedPath = ($remotePath.Trim('/').Split('/') | Where-Object { $_ } | ForEach-Object {
        [Uri]::EscapeDataString($_)
    }) -join '/'

    return "ftp://$($config.host)/$encodedPath"
}

function New-FtpDirectory([string]$remotePath) {
    $request = [System.Net.FtpWebRequest]::Create((ConvertTo-FtpUri $remotePath))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UsePassive = $true

    try {
        $response = $request.GetResponse()
        $response.Dispose()
    } catch [System.Net.WebException] {
        if (-not $_.Exception.Response -or $_.Exception.Response.StatusCode -notin @([System.Net.FtpStatusCode]::ActionNotTakenFileUnavailable, [System.Net.FtpStatusCode]::ActionNotTakenFilenameNotAllowed)) {
            throw
        }
    }
}

function Send-FtpFile([string]$localPath, [string]$remotePath) {
    $request = [System.Net.FtpWebRequest]::Create((ConvertTo-FtpUri $remotePath))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UseBinary = $true
    $request.UsePassive = $true

    $file = [System.IO.File]::OpenRead($localPath)
    $stream = $null
    try {
        $stream = $request.GetRequestStream()
        $file.CopyTo($stream)
    } finally {
        if ($stream) { $stream.Dispose() }
        $file.Dispose()
    }

    $response = $request.GetResponse()
    $response.Dispose()
}

function Send-FtpTree([string]$localRoot, [string]$remoteRoot) {
    $resolvedRoot = (Resolve-Path $localRoot).Path.TrimEnd('\')
    New-FtpDirectory $remoteRoot

    Get-ChildItem $resolvedRoot -Recurse -Directory | ForEach-Object {
        $relativePath = $_.FullName.Substring($resolvedRoot.Length).TrimStart('\').Replace('\', '/')
        New-FtpDirectory "$remoteRoot/$relativePath"
    }

    Get-ChildItem $resolvedRoot -Recurse -File | ForEach-Object {
        $relativePath = $_.FullName.Substring($resolvedRoot.Length).TrimStart('\').Replace('\', '/')
        Send-FtpFile $_.FullName "$remoteRoot/$relativePath"
        Write-Host "Uploaded photo/$relativePath"
    }
}

Send-FtpTree $PhotoRoot "$($config.appPath)/photo"
New-FtpDirectory "$($config.appPath)/seed-assets"
New-FtpDirectory "$($config.appPath)/seed-assets/properties"
Send-FtpFile $ManifestPath "$($config.appPath)/seed-assets/properties/manifest.json"

Write-Host 'Photo source and media manifest upload completed successfully.'