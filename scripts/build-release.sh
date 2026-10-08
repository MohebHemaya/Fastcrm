#!/usr/bin/env bash
set -e

echo "=== Building Driver Management System Release ==="

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
RELEASE_DIR="$ROOT_DIR/release"
FRONTEND_DIR="$ROOT_DIR/frontend"
BACKEND_DIR="$ROOT_DIR/backend"

# 1. Clean release directory
rm -rf "$RELEASE_DIR"
mkdir -p "$RELEASE_DIR/backend"
mkdir -p "$RELEASE_DIR/public_html"

# 2. Build Frontend
echo "--> Building Frontend..."
cd "$FRONTEND_DIR"
npm ci || npm install
npm run build

# 3. Copy compiled frontend to public_html
echo "--> Copying compiled frontend to public_html..."
cp -r "$FRONTEND_DIR/dist/"* "$RELEASE_DIR/public_html/"

# 4. Copy backend public assets to public_html
echo "--> Copying backend public assets..."
if [ -d "$BACKEND_DIR/public" ]; then
    cp -r "$BACKEND_DIR/public/"* "$RELEASE_DIR/public_html/" || true
fi

# 5. Overwrite public_html/index.php with Hostinger relative path
cat << 'EOF' > "$RELEASE_DIR/public_html/index.php"
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

if (file_exists($maintenance = __DIR__.'/../backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

require __DIR__.'/../backend/vendor/autoload.php';

(require_once __DIR__.'/../backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
EOF

# 6. Create production .htaccess in public_html
cat << 'EOF' > "$RELEASE_DIR/public_html/.htaccess"
<IfModule mod_rewrite.c>
    RewriteEngine On

    # Force HTTPS
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # API and Sanctum routes
    RewriteCond %{REQUEST_URI} ^/(api|sanctum|up) [NC]
    RewriteRule ^ index.php [L]

    # Static assets
    RewriteCond %{REQUEST_FILENAME} -f [OR]
    RewriteCond %{REQUEST_FILENAME} -d
    RewriteRule ^ - [L]

    # SPA rewrite fallback
    RewriteRule ^ index.html [L]
</IfModule>
EOF

# 7. Copy backend application files (excluding development folders)
echo "--> Copying backend application..."
cd "$BACKEND_DIR"
cp -r app bootstrap config database lang routes storage "$RELEASE_DIR/backend/"
cp artisan composer.json composer.lock "$RELEASE_DIR/backend/"

mkdir -p "$RELEASE_DIR/backend/storage/app"
mkdir -p "$RELEASE_DIR/backend/storage/framework/cache/data"
mkdir -p "$RELEASE_DIR/backend/storage/framework/sessions"
mkdir -p "$RELEASE_DIR/backend/storage/framework/views"
mkdir -p "$RELEASE_DIR/backend/storage/logs"
mkdir -p "$RELEASE_DIR/backend/bootstrap/cache"

# 8. Clean forbidden files from release directory
echo "--> Purging forbidden development files (.env, caches, logs, tests, git, node_modules)..."
rm -rf "$RELEASE_DIR/backend/tests" "$RELEASE_DIR/backend/node_modules"
rm -f "$RELEASE_DIR/backend/.env"* "$RELEASE_DIR/.env"* "$RELEASE_DIR/public_html/.env"*
rm -f "$RELEASE_DIR/backend/bootstrap/cache/"*.php
rm -f "$RELEASE_DIR/backend/storage/logs/"*.log
find "$RELEASE_DIR" -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true

# 9. Create release ZIP package
echo "--> Creating release.zip package..."
cd "$RELEASE_DIR"
if command -v zip >/dev/null 2>&1; then
    zip -r release.zip backend public_html
elif command -v 7z >/dev/null 2>&1; then
    7z a release.zip backend public_html
fi

# 10. Run release zip content verification
echo "--> Verifying release.zip contents..."
if [ -f "$RELEASE_DIR/release.zip" ]; then
    php "$ROOT_DIR/scripts/verify-release-zip.php" "$RELEASE_DIR/release.zip"
fi

echo "=== Release assembled successfully in $RELEASE_DIR ==="
echo "Structure:"
echo " - $RELEASE_DIR/backend      (Upload outside public_html)"
echo " - $RELEASE_DIR/public_html  (Upload inside public_html)"
echo " - $RELEASE_DIR/release.zip  (Production deployment zip archive)"

