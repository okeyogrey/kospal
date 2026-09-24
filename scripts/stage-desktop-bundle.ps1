# Stages portable PHP + a runnable Laravel tree for Tauri packaging.
# Invoked by `beforeBuildCommand` after `npm run build`.

$ErrorActionPreference = "Stop"

$Root = Split-Path -Parent $PSScriptRoot
$Resources = Join-Path $Root "src-tauri\resources"
$PhpDir = Join-Path $Resources "runtime\php"
$AppDir = Join-Path $Resources "app"
$PhpVersion = "8.4.23"
$PhpZipName = "php-$PhpVersion-Win32-vs17-x64.zip"
$PhpUrl = "https://windows.php.net/downloads/releases/$PhpZipName"
$CacheDir = Join-Path $Root "src-tauri\resources\.cache"

function Write-Step([string]$Message) {
    Write-Host "==> $Message"
}

function Ensure-Php {
    $phpExe = Join-Path $PhpDir "php.exe"
    $versionMarker = Join-Path $PhpDir ".kospal-php-version"
    $haveVersion = if (Test-Path $versionMarker) { (Get-Content $versionMarker -Raw).Trim() } else { "" }

    if ((Test-Path $phpExe) -and $haveVersion -eq $PhpVersion) {
        Write-Step "PHP $PhpVersion already staged: $phpExe"
        return
    }

    if (Test-Path $PhpDir) {
        Write-Step "Replacing staged PHP ($haveVersion -> $PhpVersion)…"
        Remove-Item $PhpDir -Recurse -Force
    }

    New-Item -ItemType Directory -Force -Path $PhpDir, $CacheDir | Out-Null
    $zipPath = Join-Path $CacheDir $PhpZipName

    if (-not (Test-Path $zipPath)) {
        Write-Step "Downloading portable PHP $PhpVersion…"
        Invoke-WebRequest -Uri $PhpUrl -OutFile $zipPath
    }

    Write-Step "Extracting PHP to $PhpDir…"
    Expand-Archive -Path $zipPath -DestinationPath $PhpDir -Force

    $iniProduction = Join-Path $PhpDir "php.ini-production"
    $iniPath = Join-Path $PhpDir "php.ini"
    if (Test-Path $iniProduction) {
        Copy-Item $iniProduction $iniPath -Force
    } else {
        New-Item -ItemType File -Path $iniPath -Force | Out-Null
    }

    $ini = Get-Content $iniPath -Raw
    $ini = $ini -replace ';?\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
    foreach ($ext in @("curl", "fileinfo", "mbstring", "openssl", "pdo_sqlite", "sqlite3")) {
        if ($ini -match "(?m)^\s*;?\s*extension\s*=\s*$ext\s*$") {
            $ini = [regex]::Replace($ini, "(?m)^\s*;?\s*extension\s*=\s*$ext\s*$", "extension=$ext")
        } else {
            $ini += "`r`nextension=$ext"
        }
    }
    Set-Content -Path $iniPath -Value $ini -Encoding ASCII
    Set-Content -Path $versionMarker -Value $PhpVersion -Encoding ASCII

    & $phpExe -v | Out-Host
}

function Sync-LaravelApp {
    Write-Step "Staging Laravel app into $AppDir…"

    if (-not (Test-Path (Join-Path $Root "vendor\autoload.php"))) {
        throw "vendor/ is missing. Run 'composer install --no-dev' before packaging."
    }
    if (-not (Test-Path (Join-Path $Root "public\build\manifest.json"))) {
        throw "public/build is missing. Run 'npm run build' before packaging."
    }

    if (Test-Path $AppDir) {
        Remove-Item $AppDir -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $AppDir | Out-Null

    $excludeDirNames = @(
        ".git",
        ".idea",
        ".vscode",
        ".zed",
        "node_modules",
        "src-tauri",
        "tests"
    )

    $robocopyArgs = @(
        $Root,
        $AppDir,
        "/E",
        "/XD"
    ) + $excludeDirNames + @(
        "/XF", ".env", ".env.backup", ".env.production", "phpunit.xml", "phpunit.xml.dist",
        "database.sqlite", "database.sqlite-journal", "database.sqlite-wal", "database.sqlite-shm",
        "hot", "laravel.log",
        "/NFL", "/NDL", "/NJH", "/NJS", "/nc", "/ns", "/np"
    )

    & robocopy @robocopyArgs | Out-Null
    $code = $LASTEXITCODE
    if ($code -ge 8) {
        throw "robocopy failed with exit code $code"
    }

    # Drop this machine's database, logs, uploads, and compiled config.
    # The desktop host creates a fresh SQLite file on first launch.
    foreach ($rel in @(
        "database\database.sqlite",
        "database\database.sqlite-journal",
        "database\database.sqlite-wal",
        "database\database.sqlite-shm",
        "public\hot"
    )) {
        $path = Join-Path $AppDir $rel
        if (Test-Path $path) {
            Remove-Item $path -Force
        }
    }

    foreach ($dir in @(
        "storage\app",
        "storage\framework\cache",
        "storage\framework\sessions",
        "storage\framework\views",
        "storage\logs",
        "bootstrap\cache"
    )) {
        $path = Join-Path $AppDir $dir
        if (Test-Path $path) {
            Get-ChildItem $path -Force -ErrorAction SilentlyContinue | Remove-Item -Recurse -Force -ErrorAction SilentlyContinue
        }
    }

    # Fresh writable dirs inside the staged tree (runtime data is copied to AppData on first launch).
    foreach ($dir in @(
        "storage\app\public",
        "storage\framework\cache\data",
        "storage\framework\sessions",
        "storage\framework\views",
        "storage\logs",
        "bootstrap\cache",
        "database"
    )) {
        New-Item -ItemType Directory -Force -Path (Join-Path $AppDir $dir) | Out-Null
    }

    $envExample = Join-Path $Root ".env.example"
    $envTarget = Join-Path $AppDir ".env"
    Copy-Item $envExample $envTarget -Force

    $envText = Get-Content $envTarget -Raw
    $envText = $envText -replace "(?m)^APP_ENV=.*$", "APP_ENV=production"
    $envText = $envText -replace "(?m)^APP_DEBUG=.*$", "APP_DEBUG=false"
    $envText = $envText -replace "(?m)^LOG_LEVEL=.*$", "LOG_LEVEL=error"
    $envText = $envText -replace "(?m)^APP_URL=.*$", "APP_URL=http://127.0.0.1:8000"
    $envText = $envText -replace "(?m)^KOSPAL_DEPLOYMENT_MODE=.*$", "KOSPAL_DEPLOYMENT_MODE=desktop"
    $envText = $envText -replace "(?m)^DB_CONNECTION=.*$", "DB_CONNECTION=sqlite"

    $rootEnv = Join-Path $Root ".env"
    $licenseSecret = ""
    $syncUrl = "https://software.kospal.com"
    if (Test-Path $rootEnv) {
        foreach ($line in Get-Content $rootEnv) {
            if ($line -match '^KOSPAL_LICENSE_SECRET=(.+)$') {
                $licenseSecret = $Matches[1].Trim().Trim('"').Trim("'")
            }
            if ($line -match '^KOSPAL_SYNC_SERVER_URL=(.+)$') {
                $candidate = $Matches[1].Trim().Trim('"').Trim("'")
                if ($candidate -ne "") {
                    $syncUrl = $candidate
                }
            }
        }
    }
    if ([string]::IsNullOrWhiteSpace($licenseSecret)) {
        throw "KOSPAL_LICENSE_SECRET is missing from .env. Add it on this computer before building the setup file."
    }

    function Set-StagedEnvLine([string]$Text, [string]$Key, [string]$Value) {
        $pattern = "(?m)^#?\s*$([regex]::Escape($Key))=.*$"
        if ($Text -match $pattern) {
            return [regex]::Replace($Text, $pattern, "$Key=$Value")
        }

        return $Text.TrimEnd() + "`r`n$Key=$Value`r`n"
    }

    $envText = Set-StagedEnvLine $envText "KOSPAL_LICENSE_SECRET" $licenseSecret
    $envText = Set-StagedEnvLine $envText "KOSPAL_SYNC_SERVER_URL" $syncUrl
    Set-Content -Path $envTarget -Value $envText -Encoding UTF8
    Write-Step "License lock and shop address written into the desktop app."

    # Marker used by the Rust host to know the staged bundle version.
    Set-Content -Path (Join-Path $AppDir ".desktop-bundle-version") -Value "0.1.1-php84" -Encoding ASCII

    Write-Step "Laravel app staged."
}

Ensure-Php
Sync-LaravelApp
Write-Step "Desktop bundle staging complete."
