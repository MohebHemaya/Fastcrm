# PowerShell Build and Release Script for Windows
$ErrorActionPreference = "Stop"

Write-Host "=== Building Driver Management System Release ===" -ForegroundColor Cyan

$RootDir = Split-Path -Parent $PSScriptRoot
$ReleaseDir = Join-Path $RootDir "release"
$FrontendDir = Join-Path $RootDir "frontend"
$BackendDir = Join-Path $RootDir "backend"

# 1. Clean release directory
if (Test-Path $ReleaseDir) {
    Remove-Item -Recurse -Force $ReleaseDir
}
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "public_html") -Force | Out-Null

# 2. Build Frontend
Write-Host "--> Building Frontend..." -ForegroundColor Yellow
Set-Location $FrontendDir
npm run build

# 3. Copy compiled frontend to public_html
Write-Host "--> Copying compiled frontend to public_html..." -ForegroundColor Yellow
Copy-Item -Path "$FrontendDir\dist\*" -Destination (Join-Path $ReleaseDir "public_html") -Recurse -Force

# 4. Copy backend public assets to public_html
Write-Host "--> Copying backend public assets..." -ForegroundColor Yellow
if (Test-Path "$BackendDir\public") {
    Copy-Item -Path "$BackendDir\public\*" -Destination (Join-Path $ReleaseDir "public_html") -Recurse -Force -Exclude "index.php", ".htaccess"
}

# 5. Write modified index.php for Hostinger
$indexPhpContent = @'
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../backend/vendor/autoload.php';

(require_once __DIR__.'/../backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
'@
Set-Content -Path (Join-Path $ReleaseDir "public_html\index.php") -Value $indexPhpContent

# 6. Write production .htaccess
$htaccessContent = @'
<IfModule mod_rewrite.c>
    RewriteEngine On

    # 1. Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # 2. Handle API, Sanctum, Health, and Webhook requests through Laravel index.php
    RewriteCond %{REQUEST_URI} ^/(api|sanctum|up|webhooks) [NC]
    RewriteRule ^ index.php [L]

    # 3. Serve existing static files directly
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # 4. Route all other requests to React SPA index.html
    RewriteRule ^ index.html [L]
</IfModule>

# Security Headers & Hardening
<IfModule mod_headers.c>
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
</IfModule>

# Prevent access to sensitive dotfiles (.env, .git)
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>
'@
Set-Content -Path (Join-Path $ReleaseDir "public_html\.htaccess") -Value $htaccessContent

# 7. Copy backend application files
Write-Host "--> Copying backend application..." -ForegroundColor Yellow
$BackendFolders = @("app", "bootstrap", "config", "database", "lang", "routes", "storage")
foreach ($folder in $BackendFolders) {
    if (Test-Path (Join-Path $BackendDir $folder)) {
        Copy-Item -Path (Join-Path $BackendDir $folder) -Destination (Join-Path $ReleaseDir "backend\$folder") -Recurse -Force
    }
}
$BackendFiles = @("artisan", "composer.json", "composer.lock")
foreach ($file in $BackendFiles) {
    if (Test-Path (Join-Path $BackendDir $file)) {
        Copy-Item -Path (Join-Path $BackendDir $file) -Destination (Join-Path $ReleaseDir "backend\$file") -Force
    }
}

# 8. Clean forbidden files from release directory
Write-Host "--> Purging forbidden files (.env, caches, logs, tests, git, node_modules)..." -ForegroundColor Yellow
$ForbiddenPaths = @(
    (Join-Path $ReleaseDir "backend\tests"),
    (Join-Path $ReleaseDir "backend\node_modules"),
    (Join-Path $ReleaseDir "frontend\node_modules")
)
foreach ($p in $ForbiddenPaths) {
    if (Test-Path $p) { Remove-Item -Recurse -Force $p }
}

Get-ChildItem -Path $ReleaseDir -Include ".env*", "*.log" -Recurse -Force -File | Remove-Item -Force
Get-ChildItem -Path (Join-Path $ReleaseDir "backend\bootstrap\cache") -Filter "*.php" -File | Remove-Item -Force
Get-ChildItem -Path $ReleaseDir -Filter ".git" -Recurse -Force -Directory | Remove-Item -Recurse -Force

# Ensure essential empty storage subdirectories exist
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\app") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\cache\data") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\sessions") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\views") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\logs") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\bootstrap\cache") -Force | Out-Null

# 9. Create release ZIP archive
Write-Host "--> Creating release.zip package..." -ForegroundColor Yellow
$ZipPath = Join-Path $ReleaseDir "release.zip"
if (Test-Path $ZipPath) {
    Remove-Item -Force $ZipPath
}

Compress-Archive -Path @(Join-Path $ReleaseDir "backend"), @(Join-Path $ReleaseDir "public_html") -DestinationPath $ZipPath -Force

# 10. Run release zip content verification
Write-Host "--> Verifying release.zip contents..." -ForegroundColor Yellow
$VerifyScript = Join-Path $RootDir "scripts\verify-release-zip.php"
$PhpBin = "php"
if ($env:PHP_BINARY) { $PhpBin = $env:PHP_BINARY }
elseif (Test-Path "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe") {
    $PhpBin = "C:\Users\Moheb Hemaya\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe"
}

& $PhpBin $VerifyScript $ZipPath
if ($LASTEXITCODE -ne 0) {
    Write-Error "Release zip verification failed!"
    exit 1
}

Set-Location $RootDir
Write-Host "=== Release assembled and verified successfully in $ReleaseDir ===" -ForegroundColor Green
Write-Host " - backend:      $(Join-Path $ReleaseDir 'backend')" -ForegroundColor White
Write-Host " - public_html:  $(Join-Path $ReleaseDir 'public_html')" -ForegroundColor White
Write-Host " - release.zip:  $ZipPath" -ForegroundColor White

