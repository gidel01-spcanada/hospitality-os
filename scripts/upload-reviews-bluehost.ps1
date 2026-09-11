param(
    [string]$CsvPath = (Join-Path $env:USERPROFILE 'Downloads\reviews.csv'),
    [string]$CredentialsPath = (Join-Path (Split-Path (Split-Path $PSScriptRoot -Parent) -Parent) '.bluehost-credentials.json'),
    [string]$RemoteFileName = 'reviews.csv'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $CsvPath)) {
    throw "CSV file not found: $CsvPath"
}
if (-not (Test-Path $CredentialsPath)) {
    throw "Credentials file not found: $CredentialsPath"
}

$config = Get-Content $CredentialsPath -Raw | ConvertFrom-Json
foreach ($property in @('host', 'username', 'password', 'appPath')) {
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
    Write-Warning 'TLS certificate validation is disabled for this upload because allowInvalidCertificate is enabled.'
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

$remoteDirectory = ($config.appPath.TrimEnd('/', '\') + '/storage/app/imports').Replace('\', '/')
$remoteFileName = [System.IO.Path]::GetFileName($RemoteFileName)
$remotePath = "$remoteDirectory/$remoteFileName"

$currentPath = $config.appPath.TrimEnd('/', '\')
foreach ($segment in @('storage', 'app', 'imports')) {
    $currentPath = ($currentPath + '/' + $segment).Replace('\', '/')
    Invoke-FtpDirectory $currentPath
}

Send-FtpFile $CsvPath $remotePath

Write-Host "Uploaded review CSV to $remotePath"
Write-Host "Then run on Bluehost from the Laravel app directory:"
Write-Host "php artisan reviews:import-booking-csv storage/app/imports/$remoteFileName --dry-run"
Write-Host "php artisan reviews:import-booking-csv storage/app/imports/$remoteFileName"
