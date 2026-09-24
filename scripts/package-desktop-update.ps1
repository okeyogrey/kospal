# Zip the staged desktop app so the website can offer it to every shop computer.
# Run scripts/stage-desktop-bundle.ps1 first. The shop's .env and database are left out.

param(
    [Parameter(Mandatory = $true)]
    [string]$Release,
    [string]$Notes = ""
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$AppDir = Join-Path $Root "src-tauri\resources\app"
$Stage = Join-Path $Root "storage\app\desktop-updates\stage"
$Zip = Join-Path $Root "storage\app\desktop-updates\kospal-desktop-src.zip"

if (-not (Test-Path (Join-Path $AppDir "artisan"))) {
    throw "Staged app not found. Run scripts/stage-desktop-bundle.ps1 first."
}

if (Test-Path $Stage) {
    Remove-Item $Stage -Recurse -Force
}

New-Item -ItemType Directory -Path $Stage -Force | Out-Null

& robocopy $AppDir $Stage /E /XF .env database.sqlite database.sqlite-journal database.sqlite-wal database.sqlite-shm /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) {
    throw "Could not copy the staged app."
}

Set-Content -Path (Join-Path $Stage ".desktop-release") -Value $Release -Encoding ASCII

if (Test-Path $Zip) {
    Remove-Item $Zip -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory($Stage, $Zip)
Remove-Item $Stage -Recurse -Force

$php = "php"
& $php (Join-Path $Root "artisan") desktop-update:publish $Zip --release=$Release --notes=$Notes
if ($LASTEXITCODE -ne 0) {
    throw "Publishing the update failed."
}
