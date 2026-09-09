param(
    [string]$CredentialsPath = (Join-Path (Split-Path $PSScriptRoot -Parent) '.bluehost-credentials.json'),
    [string]$SourceDirectory = (Join-Path (Split-Path $PSScriptRoot -Parent) 'public\uploads\properties'),
    [switch]$ConvertMov,
    [string]$FfmpegPath = 'ffmpeg'
)

$ErrorActionPreference = 'Stop'

if (-not (Test-Path $CredentialsPath -PathType Leaf)) {
    throw "Credentials file not found: $CredentialsPath"
}
if (-not (Test-Path $SourceDirectory -PathType Container)) {
    throw "Video source directory not found: $SourceDirectory"
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
        $request = [System.Net.FtpWebRequest]::Create((ConvertTo-FtpUri $remotePath))
        $request.Method = [System.Net.WebRequestMethods+Ftp]::UploadFile
        $request.Credentials = [System.Net.NetworkCredential]::new($config.username, $config.password)
        $request.EnableSsl = [bool]$config.useTls
        $request.UseBinary = $true
        $request.UsePassive = $true
        $file = $null
        $stream = $null
        try {
            $file = [System.IO.File]::OpenRead($localPath)
            $stream = $request.GetRequestStream()
            $file.CopyTo($stream)
            $stream.Dispose()
            $stream = $null
            $response = $request.GetResponse()
            $response.Dispose()
            return
        } catch {
            if ($attempt -eq 3) {
                throw
            }
            Write-Warning "Upload failed for $remotePath on attempt $attempt; retrying. $($_.Exception.Message)"
        } finally {
            if ($stream) { $stream.Dispose() }
            if ($file) { $file.Dispose() }
        }
    }
}

function Convert-MovToMp4([string]$sourcePath, [string]$targetPath) {
    if (-not (Get-Command $FfmpegPath -ErrorAction SilentlyContinue)) {
        throw "ffmpeg is required to convert $([System.IO.Path]::GetFileName($sourcePath)) to MP4. Install ffmpeg, or convert the file manually and place the MP4 in $SourceDirectory."
    }

    Write-Host "Converting $([System.IO.Path]::GetFileName($sourcePath)) to $([System.IO.Path]::GetFileName($targetPath))"
    & $FfmpegPath -hide_banner -loglevel error -y -i $sourcePath -c:v libx264 -pix_fmt yuv420p -c:a aac -movflags +faststart $targetPath
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $targetPath -PathType Leaf)) {
        throw "ffmpeg failed to create $targetPath"
    }
}

[System.Net.ServicePointManager]::SecurityProtocol = [System.Net.SecurityProtocolType]::Tls12
if ([bool]$config.allowInvalidCertificate) {
    [System.Net.ServicePointManager]::ServerCertificateValidationCallback = { $true }
    Write-Warning 'TLS certificate validation is disabled because allowInvalidCertificate is enabled.'
}

$temporaryDirectory = Join-Path ([System.IO.Path]::GetTempPath()) ('hospitality-os-video-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Force -Path $temporaryDirectory | Out-Null
$remoteDirectory = "$($config.publicPath.TrimEnd('/'))/uploads/properties"

try {
    New-FtpDirectory "$($config.publicPath.TrimEnd('/'))/uploads"
    New-FtpDirectory $remoteDirectory

    foreach ($number in 401..404) {
        $targetName = "$number.mp4"
        $targetPath = Join-Path $SourceDirectory $targetName
        $temporaryTarget = Join-Path $temporaryDirectory $targetName

        if (-not (Test-Path $targetPath -PathType Leaf)) {
            $movPath = Join-Path $SourceDirectory "$number.mov"
            if (-not (Test-Path $movPath -PathType Leaf)) {
                throw "Missing video for apartment $number. Expected $targetPath or $movPath."
            }
            if (-not $ConvertMov) {
                throw "Missing $targetName. Found $([System.IO.Path]::GetFileName($movPath)); rerun with -ConvertMov after installing ffmpeg, or convert it manually to MP4."
            }
            Convert-MovToMp4 $movPath $temporaryTarget
            $targetPath = $temporaryTarget
        }

        $remotePath = "$remoteDirectory/$targetName"
        Write-Host "Uploading $targetName to $remotePath"
        Send-FtpFile $targetPath $remotePath
    }
}
finally {
    Remove-Item $temporaryDirectory -Recurse -Force -ErrorAction SilentlyContinue
}

Write-Host 'Bluehost property video upload completed successfully.'
