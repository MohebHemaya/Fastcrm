# PowerShell script to prepare the turnkey Hostinger deployment package
$ErrorActionPreference = "Stop"

Write-Host "=== Preparing Hostinger Turnkey Deployment Package ===" -ForegroundColor Cyan

$RootDir = Split-Path -Parent $PSScriptRoot
$ReleaseDir = Join-Path $RootDir "release"
$FrontendDir = Join-Path $RootDir "frontend"
$BackendDir = Join-Path $RootDir "backend"

# 1. Ensure target directories exist
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "public_html") -Force | Out-Null

# 2. Copy compiled frontend to release/public_html
Write-Host "--> Copying compiled frontend dist to public_html..." -ForegroundColor Yellow
Copy-Item -Path "$FrontendDir\dist\*" -Destination (Join-Path $ReleaseDir "public_html") -Recurse -Force

# 3. Copy backend public static assets
Write-Host "--> Copying backend public static assets..." -ForegroundColor Yellow
if (Test-Path "$BackendDir\public") {
    Copy-Item -Path "$BackendDir\public\*" -Destination (Join-Path $ReleaseDir "public_html") -Recurse -Force -Exclude "index.php", ".htaccess"
}

# 4. Write Hostinger index.php
Write-Host "--> Writing public_html/index.php..." -ForegroundColor Yellow
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
Set-Content -Path (Join-Path $ReleaseDir "public_html\index.php") -Value $indexPhpContent -Encoding utf8

# 5. Write Hostinger .htaccess
Write-Host "--> Writing public_html/.htaccess..." -ForegroundColor Yellow
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
Set-Content -Path (Join-Path $ReleaseDir "public_html\.htaccess") -Value $htaccessContent -Encoding utf8

# 6. Copy backend application folders
Write-Host "--> Copying backend core folders..." -ForegroundColor Yellow
$BackendFolders = @("app", "bootstrap", "config", "database", "lang", "routes", "storage", "vendor")
foreach ($folder in $BackendFolders) {
    $src = Join-Path $BackendDir $folder
    $dest = Join-Path $ReleaseDir "backend\$folder"
    if (Test-Path $src) {
        Write-Host "    Copying $folder..." -ForegroundColor DarkGray
        Copy-Item -Path $src -Destination $dest -Recurse -Force
    }
}

$BackendFiles = @("artisan", "composer.json", "composer.lock")
foreach ($file in $BackendFiles) {
    $src = Join-Path $BackendDir $file
    $dest = Join-Path $ReleaseDir "backend\$file"
    if (Test-Path $src) {
        Copy-Item -Path $src -Destination $dest -Force
    }
}

# 7. Clean caches and tests in backend release
Write-Host "--> Purging runtime caches, logs, tests from release/backend..." -ForegroundColor Yellow
$purgePaths = @(
    (Join-Path $ReleaseDir "backend\tests"),
    (Join-Path $ReleaseDir "backend\node_modules")
)
foreach ($p in $purgePaths) {
    if (Test-Path $p) { Remove-Item -Recurse -Force $p }
}

Get-ChildItem -Path (Join-Path $ReleaseDir "backend\bootstrap\cache") -Filter "*.php" -File -ErrorAction SilentlyContinue | Remove-Item -Force
Get-ChildItem -Path (Join-Path $ReleaseDir "backend\storage\logs") -Filter "*.log" -File -ErrorAction SilentlyContinue | Remove-Item -Force

# Ensure storage directories exist
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\app\exports") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\app\backups") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\cache\data") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\sessions") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\framework\views") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\storage\logs") -Force | Out-Null
New-Item -ItemType Directory -Path (Join-Path $ReleaseDir "backend\bootstrap\cache") -Force | Out-Null

# 8. Write tailored production .env
Write-Host "--> Writing tailored production .env for Hostinger..." -ForegroundColor Yellow
$envContent = @'
APP_NAME="نظام إدارة السائقين"
APP_ENV=production
APP_KEY=base64:lN7zCdbv7DoBChYTTjLdBqg2jsAhtHBnHdllA6+9Dy0=
APP_DEBUG=false
APP_TIMEZONE=Africa/Cairo
APP_URL=https://erp.zimpact-eg.com

APP_LOCALE=ar
APP_FALLBACK_LOCALE=ar
APP_FAKER_LOCALE=ar_SA

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=daily
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=u340991755_Fast_CRM
DB_USERNAME=u340991755_Fast_CRM
DB_PASSWORD=Saba7g@mil
DB_CHARSET=utf8mb4
DB_COLLATION=utf8mb4_unicode_ci

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=database
CACHE_STORE=database

SANCTUM_STATEFUL_DOMAINS=erp.zimpact-eg.com
CORS_ALLOWED_ORIGINS=https://erp.zimpact-eg.com
FRONTEND_URL=https://erp.zimpact-eg.com

WHATSAPP_PROVIDER=ultramsg
WHATSAPP_VERIFY_TOKEN=fastcrm_zimpact_2026
ULTRAMSG_BASE_URL=https://api.ultramsg.com
ULTRAMSG_INSTANCE_ID=instance194026
ULTRAMSG_TOKEN=962ei4qk35vt83ir

# Initial Admin Seeding (Credentials)
ADMIN_EMAIL=admin@erp.zimpact-eg.com
ADMIN_PASSWORD=Saba7g@mil
ADMIN_NAME="مدير النظام"
'@

Set-Content -Path (Join-Path $ReleaseDir "backend\.env") -Value $envContent -Encoding utf8
Set-Content -Path (Join-Path $ReleaseDir "backend\.env.production") -Value $envContent -Encoding utf8

# 9. Create zip packages for fast upload in Hostinger File Manager
Write-Host "--> Creating public_html.zip..." -ForegroundColor Yellow
$publicHtmlZip = Join-Path $ReleaseDir "public_html.zip"
if (Test-Path $publicHtmlZip) { Remove-Item -Force $publicHtmlZip }
Compress-Archive -Path (Join-Path $ReleaseDir "public_html\*") -DestinationPath $publicHtmlZip -Force

Write-Host "--> Creating backend.zip..." -ForegroundColor Yellow
$backendZip = Join-Path $ReleaseDir "backend.zip"
if (Test-Path $backendZip) { Remove-Item -Force $backendZip }
Compress-Archive -Path (Join-Path $ReleaseDir "backend") -DestinationPath $backendZip -Force

Write-Host "--> Creating full_release.zip..." -ForegroundColor Yellow
$fullReleaseZip = Join-Path $ReleaseDir "full_release.zip"
if (Test-Path $fullReleaseZip) { Remove-Item -Force $fullReleaseZip }
Compress-Archive -Path @(Join-Path $ReleaseDir "backend"), @(Join-Path $ReleaseDir "public_html"), (Join-Path $ReleaseDir "database.sql") -DestinationPath $fullReleaseZip -Force

Write-Host "=== Deployment Package Ready in $ReleaseDir ===" -ForegroundColor Green
Write-Host " 1. public_html folder:  $(Join-Path $ReleaseDir 'public_html')" -ForegroundColor White
Write-Host " 2. public_html.zip:     $publicHtmlZip" -ForegroundColor White
Write-Host " 3. backend folder:      $(Join-Path $ReleaseDir 'backend')" -ForegroundColor White
Write-Host " 4. backend.zip:         $backendZip" -ForegroundColor White
Write-Host " 5. database.sql:        $(Join-Path $ReleaseDir 'database.sql')" -ForegroundColor White
Write-Host " 6. full_release.zip:    $fullReleaseZip" -ForegroundColor White
