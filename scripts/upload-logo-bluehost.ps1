param(
    # Path to the logo image on this computer (PNG, JPG, WEBP or SVG).
    [Parameter(Mandatory = $true)]
    [string]$LogoPath,
    # File name used on the server (extension is taken from the source file).
    [string]$RemoteName = 'afrikappart-logo',
    # Folder under the Bluehost public folder where the logo is stored.
    [string]$RemoteFolder = 'uploads/branding',
    [string]$CredentialsPath,
    # Public site address (e.g. https://www.example.com), used to print the final logo URL.
    [string]$SiteUrl,
    # Also copy the logo into the local public folder so local and production use the same path.
    [switch]$CopyLocally
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path $PSScriptRoot -Parent

if (-not $CredentialsPath) {
    # Same lookup as the other Bluehost scripts: repo root first, then one level above (never committed).
    $CredentialsPath = @(
        (Join-Path $repoRoot '.bluehost-credentials.json'),
        (Join-Path (Split-Path $repoRoot -Parent) '.bluehost-credentials.json')
    ) | Where-Object { Test-Path $_ -PathType Leaf } | Select-Object -First 1
}
if (-not $CredentialsPath -or -not (Test-Path $CredentialsPath -PathType Leaf)) {
    throw 'Credentials file .bluehost-credentials.json not found (looked in the repo root and its parent). Pass -CredentialsPath.'
}
if (-not (Test-Path $LogoPath -PathType Leaf)) {
    throw "Logo file not found: $LogoPath"
}

$extension = [System.IO.Path]::GetExtension($LogoPath).ToLowerInvariant()
if ($extension -notin @('.png', '.jpg', '.jpeg', '.webp', '.svg')) {
    throw "Unsupported logo format '$extension'. Use PNG, JPG, WEBP or SVG."
}
$logoSize = (Get-Item $LogoPath).Length
if ($logoSize -gt 2MB) {
    throw "Logo is $([math]::Round($logoSize / 1MB, 2)) MB. Please use an image under 2 MB."
}
if ($RemoteName -notmatch '^[A-Za-z0-9._-]+$' -or $RemoteFolder -notmatch '^[A-Za-z0-9/_-]+$') {
    throw 'RemoteName and RemoteFolder may only contain letters, digits, dot, dash, underscore (and / for the folder).'
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

function New-FtpRequest([string]$remotePath, [string]$method) {
    $request = [System.Net.FtpWebRequest]::Create((ConvertTo-FtpUri $remotePath))
    $request.Method = $method
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UseBinary = $true
    $request.UsePassive = $true
    return $request
}

function New-FtpDirectory([string]$remotePath) {
    try {
        $response = (New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse()
        $response.Dispose()
    } catch [System.Net.WebException] {
        # "Already exists" is reported as an unavailable/not-allowed action; anything else is a real error.
        if (-not $_.Exception.Response -or $_.Exception.Response.StatusCode -notin @(
            [System.Net.FtpStatusCode]::ActionNotTakenFileUnavailable,
            [System.Net.FtpStatusCode]::ActionNotTakenFilenameNotAllowed
        )) {
            throw
        }
    }
}

function Send-FtpFile([string]$localPath, [string]$remotePath) {
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        $file = $null
        $stream = $null
        try {
            $request = New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::UploadFile)
            $file = [System.IO.File]::OpenRead($localPath)
            $stream = $request.GetRequestStream()
            $file.CopyTo($stream)
            $stream.Dispose()
            $stream = $null
            $response = $request.GetResponse()
            $response.Dispose()
            return
        } catch {
            if ($attempt -eq 3) { throw }
            Write-Warning "Upload attempt $attempt failed; retrying. $($_.Exception.Message)"
        } finally {
            if ($stream) { $stream.Dispose() }
            if ($file) { $file.Dispose() }
        }
    }
}

function Get-FtpFileSize([string]$remotePath) {
    $response = (New-FtpRequest $remotePath ([System.Net.WebRequestMethods+Ftp]::GetFileSize)).GetResponse()
    try { return $response.ContentLength } finally { $response.Dispose() }
}

$fileName = "$RemoteName$extension"
$relativePath = "$($RemoteFolder.Trim('/'))/$fileName"
$publicRoot = $config.publicPath.TrimEnd('/')

# Create each folder level (e.g. uploads, then uploads/branding).
$currentPath = $publicRoot
foreach ($segment in $RemoteFolder.Trim('/').Split('/')) {
    $currentPath = "$currentPath/$segment"
    New-FtpDirectory $currentPath
}

$remotePath = "$publicRoot/$relativePath"
Write-Host "Uploading $LogoPath -> $remotePath"
Send-FtpFile (Resolve-Path $LogoPath).Path $remotePath

$remoteSize = Get-FtpFileSize $remotePath
if ($remoteSize -ne $logoSize) {
    throw "Upload verification failed: local size $logoSize bytes, remote size $remoteSize bytes."
}
Write-Host "Upload verified ($remoteSize bytes)."

if ($CopyLocally) {
    $localTarget = Join-Path $repoRoot (Join-Path 'public' ($relativePath -replace '/', [System.IO.Path]::DirectorySeparatorChar))
    New-Item -ItemType Directory -Force -Path (Split-Path $localTarget -Parent) | Out-Null
    Copy-Item -LiteralPath $LogoPath -Destination $localTarget -Force
    Write-Host "Copied locally to $localTarget"
}

Write-Host ''
Write-Host 'Done. To use this logo on the site, in emails and as browser icon:'
$SiteUrl = if ($SiteUrl) { $SiteUrl } elseif ($config.siteUrl) { $config.siteUrl } else { $null }
if ($SiteUrl) {
    $logoUrl = "$($SiteUrl.TrimEnd('/'))/$relativePath"
    Write-Host "  1. Open it in a browser to check: $logoUrl"
    Write-Host "  2. Admin > Settings > 'URL de l'icone de l'onglet du navigateur' (site_favicon_url) = $logoUrl"
} else {
    Write-Host "  Logo URL: https://<your-domain>/$relativePath (rerun with -SiteUrl to print the exact URL)"
    Write-Host "  Paste that full URL in Admin > Settings > 'URL de l'icone de l'onglet du navigateur' (site_favicon_url)."
}
