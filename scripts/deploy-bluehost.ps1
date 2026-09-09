param(
    # Credentials stay one level above the repo (in the workspace root) so they're never inside version control.
    [string]$CredentialsPath = (Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) '.bluehost-credentials.json'),
    [string]$ReleaseRoot = (Join-Path (Split-Path $PSScriptRoot -Parent) 'release'),
    [switch]$CleanRemoteDevelopmentTrees
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $CredentialsPath)) {
    throw "Credentials file not found: $CredentialsPath"
}
if (-not (Test-Path $ReleaseRoot)) {
    throw "Release directory not found: $ReleaseRoot. Build the release first."
}

$config = Get-Content $CredentialsPath -Raw | ConvertFrom-Json
foreach ($property in @('host', 'username', 'password', 'appPath', 'publicPath')) {
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
    Write-Warning 'TLS certificate validation is disabled for this deployment because allowInvalidCertificate is enabled.'
}

function Get-FtpUri([string]$remotePath) {
    $normalized = $remotePath.Trim('/').Replace('\', '/')
    return "ftp://$($config.host)/$normalized"
}

function Invoke-FtpDirectory([string]$remotePath) {
    $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri $remotePath))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::MakeDirectory
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UsePassive = $true
    try {
        $response = $request.GetResponse()
        $response.Dispose()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode -notin @([System.Net.FtpStatusCode]::ActionNotTakenFileUnavailable, [System.Net.FtpStatusCode]::ActionNotTakenFilenameNotAllowed)) {
            throw
        }
    }
}

function Send-FtpFile([string]$localPath, [string]$remotePath) {
    $lastError = $null
    for ($attempt = 1; $attempt -le 3; $attempt++) {
        $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri $remotePath))
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
        $request.EnableSsl = [bool]$config.useTls
        $request.UseBinary = $true
        $request.UsePassive = $true
        $stream = $null
        $file = $null
        $response = $null
        try {
            $file = [System.IO.File]::OpenRead($localPath)
            $stream = $request.GetRequestStream()
            $file.CopyTo($stream)
            $stream.Close()
            $stream = $null
            $response = $request.GetResponse()
            $response.Close()

            return
        } catch {
            $lastError = $_
            if ($attempt -eq 3) {
                throw
            }
            Write-Warning "Upload failed for $remotePath on attempt $attempt; retrying. $($_.Exception.Message)"
        } finally {
            if ($response) { try { $response.Close() } catch { } }
            if ($stream) { try { $stream.Close() } catch { } }
            if ($file) { try { $file.Close() } catch { } }
        }
    }

    if ($lastError) { throw $lastError }
}

function Remove-FtpFile([string]$remotePath) {
    $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri $remotePath))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::DeleteFile
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UsePassive = $true
    try {
        $response = $request.GetResponse()
        $response.Dispose()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode -ne [System.Net.FtpStatusCode]::ActionNotTakenFileUnavailable) { throw }
    }
}

function Remove-FtpDirectoryFromLocalTree([string]$localRoot, [string]$remoteRoot) {
    $files = Get-ChildItem $localRoot -Recurse -File -Force | Sort-Object FullName -Descending
    foreach ($file in $files) {
        $relative = $file.FullName.Substring($localRoot.Length).TrimStart('\', '/')
        Remove-FtpFile "$remoteRoot/$($relative.Replace('\', '/'))"
    }
    $directories = Get-ChildItem $localRoot -Recurse -Directory -Force | Sort-Object FullName -Descending
    foreach ($directory in $directories) {
        $relative = $directory.FullName.Substring($localRoot.Length).TrimStart('\', '/')
        $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri "$remoteRoot/$($relative.Replace('\', '/'))"))
        $request.Method = [System.Net.WebRequestMethods+Ftp]::RemoveDirectory
        $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
        $request.EnableSsl = [bool]$config.useTls
        $request.UsePassive = $true
        try { $response = $request.GetResponse(); $response.Dispose() } catch [System.Net.WebException] { }
    }
    $request = [System.Net.FtpWebRequest]::Create((Get-FtpUri $remoteRoot))
    $request.Method = [System.Net.WebRequestMethods+Ftp]::RemoveDirectory
    $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
    $request.EnableSsl = [bool]$config.useTls
    $request.UsePassive = $true
    try { $response = $request.GetResponse(); $response.Dispose() } catch [System.Net.WebException] { }
}

function Publish-Directory([string]$localRoot, [string]$remoteRoot) {
    Invoke-FtpDirectory $remoteRoot
    $items = Get-ChildItem $localRoot -Recurse -Force
    foreach ($item in $items) {
        if ($item.Name -in @('.git', '.gitignore')) {
            continue
        }
        $relative = $item.FullName.Substring($localRoot.Length).TrimStart('\', '/')
        if ($relative -match '^vendor[\\/]nesbot[\\/]carbon[\\/]src[\\/]Carbon[\\/]Lang[\\/]' -and $item.Name -notin @('en.php', 'fr.php')) {
            continue
        }
        if ($item.Name -in @('.env', '.env.example') -or $relative -match '(^|[\\/])\.env($|\.)') {
            continue
        }
        if ($localRoot -like '*\\release\\app' -and ($relative -eq 'public' -or $relative.StartsWith('public\\'))) {
            continue
        }
        $remotePath = "$remoteRoot/$($relative.Replace('\', '/'))"
        if ($item.PSIsContainer) {
            Invoke-FtpDirectory $remotePath
        } else {
            Send-FtpFile $item.FullName $remotePath
            Write-Host "Uploaded $relative"
        }
    }
}

function Get-RelativeRemotePath([string]$fromPath, [string]$toPath) {
    $fromParts = @($fromPath.Trim('/').Split('/') | Where-Object { $_ })
    $toParts = @($toPath.Trim('/').Split('/') | Where-Object { $_ })
    $index = 0
    while ($index -lt $fromParts.Count -and $index -lt $toParts.Count -and $fromParts[$index] -eq $toParts[$index]) {
        $index++
    }

    $relativeParts = @()
    for ($i = $index; $i -lt $fromParts.Count; $i++) {
        $relativeParts += '..'
    }
    for ($i = $index; $i -lt $toParts.Count; $i++) {
        $relativeParts += $toParts[$i]
    }

    if ($relativeParts.Count -eq 0) {
        return '.'
    }

    return ($relativeParts -join '/')
}

function New-PublicUploadRoot() {
    $publicSource = Join-Path $ReleaseRoot 'public'
    foreach ($requiredFile in @('robots.txt', 'sitemap.xml')) {
        if (-not (Test-Path (Join-Path $publicSource $requiredFile))) {
            throw "Required public SEO file is missing from the release: $requiredFile"
        }
    }

    $publicUploadRoot = Join-Path ([System.IO.Path]::GetTempPath()) ('hospitality-os-public-' + [guid]::NewGuid().ToString('N'))
    Remove-Item $publicUploadRoot -Recurse -Force -ErrorAction SilentlyContinue
    New-Item -ItemType Directory -Force -Path $publicUploadRoot | Out-Null
    Copy-Item (Join-Path $publicSource '*') $publicUploadRoot -Recurse -Force

    $indexPath = Join-Path $publicUploadRoot 'index.php'
    if (Test-Path $indexPath) {
        $appRelativePath = Get-RelativeRemotePath $config.publicPath $config.appPath
        $index = Get-Content $indexPath -Raw
        $index = $index.Replace("__DIR__.'/../storage/", "__DIR__.'/$appRelativePath/storage/")
        $index = $index.Replace("__DIR__.'/../vendor/", "__DIR__.'/$appRelativePath/vendor/")
        $index = $index.Replace("__DIR__.'/../bootstrap/", "__DIR__.'/$appRelativePath/bootstrap/")
        Set-Content -Path $indexPath -Value $index -NoNewline
    }

    return $publicUploadRoot
}

if ($CleanRemoteDevelopmentTrees) {
    Remove-FtpDirectoryFromLocalTree (Join-Path (Split-Path $PSScriptRoot -Parent) '.git') "$($config.appPath)/.git"
    Remove-FtpDirectoryFromLocalTree (Join-Path (Split-Path $PSScriptRoot -Parent) 'node_modules') "$($config.appPath)/node_modules"
}
foreach ($sensitiveFile in @('.bluehost-credentials.json', '.bluehost-credentials.template.json')) {
    Remove-FtpFile "$($config.appPath)/$sensitiveFile"
}
Remove-FtpFile "$($config.publicPath)/hot"
Remove-FtpFile "$($config.appPath)/public/hot"
Publish-Directory (Join-Path $ReleaseRoot 'app') $config.appPath
$appBuildRoot = Join-Path $ReleaseRoot 'public\build'
if (-not (Test-Path (Join-Path $appBuildRoot 'manifest.json'))) {
    throw "Vite manifest not found at $appBuildRoot. Build the release before deploying."
}
Publish-Directory $appBuildRoot "$($config.appPath)/public/build"
$publicUploadRoot = New-PublicUploadRoot
try {
    Publish-Directory $publicUploadRoot $config.publicPath
} finally {
    Remove-Item $publicUploadRoot -Recurse -Force -ErrorAction SilentlyContinue
}
Write-Host 'Bluehost deployment completed without uploading .env files.'
