# Hostinger Shared Hosting (hPanel) Deployment Guide

This document details the complete deployment runbook, directory architecture, file structure, `.htaccess` routing rules, secrets management, cron job configuration, update procedure, and rollback strategy for running the **Driver Management System** on Hostinger shared hosting.

---

## 1. Directory Structure

Hostinger shared hosting separates public web assets from private application code:

```
/home/u123456789/
├── backend/                       # Laravel core application (OUTSIDE public_html)
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── routes/
│   ├── storage/                   # Private storage (chmod -R 775)
│   │   ├── app/
│   │   │   ├── exports/           # Private driver exports (requires auth)
│   │   │   └── backups/           # Encrypted database backups
│   │   ├── framework/
│   │   └── logs/
│   ├── vendor/
│   ├── artisan
│   └── .env                       # Production environment configuration (chmod 600)
│
└── public_html/                   # Publicly accessible root
    ├── assets/                    # Compiled React assets (CSS, JS, Fonts)
    ├── index.html                 # React SPA entry point
    ├── index.php                  # Laravel entry point (routes requests to backend/)
    ├── .htaccess                  # Apache routing and security headers
    ├── favicon.ico
    └── robots.txt
```

---

## 2. `.htaccess` Routing & Security Rules

Create `/home/u123456789/public_html/.htaccess` with the following configuration:

```apache
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
```

---

## 3. Laravel Entry Point (`public_html/index.php`)

In `public_html/index.php`, update the paths to point to the `backend/` folder located outside `public_html`:

```php
<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Maintenance mode check
if (file_exists($maintenance = __DIR__.'/../backend/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Composer autoloader
require __DIR__.'/../backend/vendor/autoload.php';

// Bootstrap Laravel application
(require_once __DIR__.'/../backend/bootstrap/app.php')
    ->handleRequest(Request::capture());
```

---

## 4. Production Cron Job (hPanel)

Because Hostinger shared hosting does not permit long-running background processes or daemon queue workers, all background processing (queued notifications, imports, questionnaire reminders, scheduled backups) runs on a 1-minute schedule via cron.

> [!WARNING]
> **CLI vs. Web PHP Version Discrepancy:**
> On Hostinger shared hosting, the default system `/usr/bin/php` can point to an older PHP version (such as PHP 7.4 or 8.1) while your website runs PHP 8.3 via LiteSpeed/PHP-FPM. If your cron job executes with an older PHP binary, Laravel commands will fail or throw syntax errors. You **must** discover and use the exact PHP 8.3 CLI binary path.

### Discovering the Exact PHP 8.3 CLI Path

1. **Via SSH:**
   Log into your Hostinger shell and check available PHP CLI binaries:
   ```bash
   # Check the default CLI version
   php -v

   # Find binary location
   which php

   # If default 'php' is not 8.3+, test specific versioned binaries:
   /usr/bin/php8.3 -v
   /usr/local/bin/php8.3 -v
   /opt/alt/php83/usr/bin/php -v
   ```
   Choose the binary that outputs `PHP 8.3.x` (or `8.4.x`).
2. **Via hPanel:**
   Navigate to **hPanel > Advanced > PHP Configuration** to confirm the active web PHP version is set to 8.3. Then check **hPanel > Advanced > Cron Jobs** — Hostinger often displays the exact command syntax or provides a dropdown/hint with the PHP 8.3 CLI path for scheduled tasks.

### Configuring the hPanel Cron Job

In **hPanel > Advanced > Cron Jobs**, select **Custom** with schedule:
```
* * * * *
```
Command (replace `<PHP_83_CLI_PATH>` with your discovered binary, e.g., `/usr/bin/php8.3` or `/opt/alt/php83/usr/bin/php`, and `/home/u123456789/backend` with your absolute backend path):
```bash
cd /home/u123456789/backend && <PHP_83_CLI_PATH> artisan schedule:run >> /dev/null 2>&1
```

### Deployment Smoke Check (Cron Binary Verification)

Before enabling the cron job, run this smoke verification via SSH using the exact binary path intended for cron:
```bash
# 1. Verify PHP binary version is 8.3+
<PHP_83_CLI_PATH> -v

# 2. Verify Laravel environment and configuration using that binary
cd /home/u123456789/backend && <PHP_83_CLI_PATH> artisan about
```
Ensure `artisan about` runs without error, shows `PHP Version: 8.3.x`, and reports the environment as `production`.

---

## 5. Storage Directory Permissions & `storage:link` Alternative

### Directory Permissions
Ensure private directories have proper write permissions:
```bash
chmod -R 775 /home/u123456789/backend/storage
chmod -R 775 /home/u123456789/backend/bootstrap/cache
chmod 600 /home/u123456789/backend/.env
```

### `storage:link` Alternative on Shared Hosting
On Hostinger shared hosting, the PHP `symlink()` function may be disabled or restricted in `php.ini`. The Driver Management System is engineered so that **no public symlink is needed**:

1. **Private Exports:** Excel and CSV driver exports are generated into `storage/app/exports/`. They are never publicly accessible. Downloads are served through the authenticated API route:
   `GET /api/v1/drivers/export/download/{filename}`
   The controller validates user permissions, checks file ownership, and streams the file using `Storage::disk('local')->download()`.
2. **Database Backups:** Backups are stored in `storage/app/backups/` and encrypted with `APP_KEY`. Downloads are served only to authenticated Super Administrators through:
   `GET /api/v1/admin/health/backups/download/{filename}`
3. **No Direct Public Storage Access:** Because zero sensitive assets live in `public/storage`, symlink configuration is completely bypassed, preventing unauthorized file scraping.

---

## 6. PHP Version & Extensions (hPanel Configuration)

In **hPanel > Advanced > PHP Configuration**:
- **PHP Version:** Select **PHP 8.3** (or PHP 8.4). Laravel strictly requires **PHP >= 8.3**.
- **Required Extensions:** Ensure the following extensions are active:
  - `pdo_mysql`
  - `mbstring`
  - `intl`
  - `zip`
  - `gd`
  - `fileinfo`
  - `openssl`
  - `curl`

---

## 7. WhatsApp Webhook Configuration

The single public URL for Meta WhatsApp Webhooks is:
```text
https://your-domain.com/webhooks/whatsapp
```

- **Methods:** `GET` (verification handshake) and `POST` (inbound messages and status receipts).
- **Security:** Excluded from CSRF validation and Sanctum authentication in `bootstrap/app.php`. Authenticity is verified using Meta's HMAC-SHA256 signature against `WHATSAPP_APP_SECRET`.
- **Single Public URL:** The webhook is registered strictly once in `routes/web.php` at `/webhooks/whatsapp`. The duplicate path `/api/v1/webhooks/whatsapp` is excluded and returns `404 Not Found`.

---

## 8. Secrets & Recovery

Managing credentials securely on shared hosting is critical for operational resilience and business continuity.

### Storage of `APP_KEY`
> [!CAUTION]
> `APP_KEY` is the master cryptographic key (AES-256-CBC) for Laravel. In this system, automated database backups generated by `php artisan app:backup-db` are encrypted using `APP_KEY`.
> - **Never** store `APP_KEY` solely inside `.env` or within the backup directory itself.
> - **Must** be stored separately in an off-site enterprise password manager (e.g., 1Password, Bitwarden, KeePass) along with the recovery runbook.
> - If the server is lost or compromised, encrypted database dumps **cannot be decrypted** without this key.

### Complete Secrets Inventory & Safe Rotation Procedures

| Secret | Purpose | Rotation Strategy & Zero-Downtime Steps |
| :--- | :--- | :--- |
| **`APP_KEY`** | Encrypts sessions, cookies, cached tokens, and automated database backups (`.sql.gz.enc`). | 1. Generate new key: `php artisan key:generate --show`.<br>2. Note: rotating `APP_KEY` invalidates existing user sessions and prevents decrypting past backup files unless the old key is archived.<br>3. Before rotating, run an unencrypted backup or archive the old key in your password manager.<br>4. Update `APP_KEY` in `backend/.env`.<br>5. Run `php artisan config:cache`. |
| **`DB_PASSWORD`** | Connects Laravel to the MySQL database. | 1. In Hostinger hPanel > Databases > MySQL Databases, create a secondary database user or update the current user's password.<br>2. Update `DB_PASSWORD` in `backend/.env`.<br>3. Clear config cache: `php artisan config:cache`.<br>4. Test connectivity via `php artisan tinker --execute="DB::connection()->getPdo();"`.<br>5. Zero downtime is achieved with immediate configuration reload. |
| **`WHATSAPP_TOKEN`** | System User Access Token for Meta Graph API calls. | 1. In Meta Business Manager > System Users, generate a new access token with `whatsapp_business_messaging` permissions.<br>2. Do **not** delete or revoke the previous token yet.<br>3. Update `WHATSAPP_TOKEN` in `backend/.env`.<br>4. Run `php artisan config:cache`.<br>5. Send a test WhatsApp message to verify the new token works.<br>6. Revoke the old token in Meta Business Manager. (100% zero-downtime rotation). |
| **`WHATSAPP_APP_SECRET`** | Verifies HMAC-SHA256 signatures (`X-Hub-Signature-256`) of incoming webhooks. | 1. In Meta Developer Portal > App Settings > Basic, locate App Secret.<br>2. Generate a new secret (or use Meta's rolling secret feature).<br>3. Update `WHATSAPP_APP_SECRET` in `backend/.env`.<br>4. Run `php artisan config:cache`. |
| **`WHATSAPP_VERIFY_TOKEN`** | Challenge verification token during webhook setup. | 1. Generate a high-entropy string: `openssl rand -hex 32`.<br>2. Update `WHATSAPP_VERIFY_TOKEN` in `backend/.env`.<br>3. Run `php artisan config:cache`.<br>4. In Meta Developer Portal > WhatsApp > Configuration, click "Edit" on Webhook URL, paste the new token, and click "Verify and Save". |
| **Admin Passwords** | Authentication for admin and operator accounts. | 1. Reset via application UI under Settings / Profile.<br>2. Or via CLI emergency reset:<br>`php artisan tinker --execute="$u = App\Models\User::where('email', 'admin@example.com')->first(); $u->password = Hash::make('NewStrongPassword123!'); $u->save();"`<br>3. Active Sanctum tokens for the user can be revoked in the database if compromise is suspected: `DB::table('personal_access_tokens')->where('tokenable_id', $u->id)->delete();`. |

---

## 9. Initial Deployment Runbook

Follow these exact steps when deploying the application for the first time:

1. **Build Frontend Locally / in CI:**
   ```bash
   cd frontend
   npm ci
   npm run build
   ```
2. **Prepare Backend Archive:**
   ```bash
   cd backend
   composer install --no-dev --optimize-autoloader
   ```
3. **Upload Files to Hostinger:**
   - Upload backend contents to `/home/u123456789/backend/`.
   - Upload frontend `dist/` contents to `/home/u123456789/public_html/`.
   - Copy `backend/public/index.php` to `public_html/index.php` and update the bootstrap paths as shown in Section 3.
   - Upload `.htaccess` as shown in Section 2.
4. **Configure Environment:**
   - Copy `.env.example` to `/home/u123456789/backend/.env`.
   - Set database credentials, app URL (`APP_URL=https://your-domain.com`), and WhatsApp keys.
   - Run `php artisan key:generate`.
5. **Run Migrations & Seed Baseline Data:**
   ```bash
   cd /home/u123456789/backend
   php artisan migrate --force
   php artisan db:seed --class=AdminUserSeeder --force
   ```
6. **Optimize Caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
7. **Verify Cron Binary & Setup Cron:**
   - Execute the deployment smoke check via SSH using your discovered PHP 8.3 CLI binary:
     ```bash
     <PHP_83_CLI_PATH> -v
     cd /home/u123456789/backend && <PHP_83_CLI_PATH> artisan about
     ```
   - Add the `schedule:run` entry to hPanel Cron Jobs as described in Section 4.
8. **Verify Setup:**
   - Visit `https://your-domain.com/up` -> returns `200 OK`.
   - Log in to `/login` and check `/api/v1/admin/health`.

---

## 10. Minimal Downtime Update Procedure

To deploy updates without interrupting active users:

1. **Build and Test:**
   - Run CI test suite (Pest, Pint, PHPStan, Vitest, Playwright).
   - Compile production frontend assets (`npm run build`).
2. **Maintenance Window (if migrations alter large tables):**
   ```bash
   php artisan down --secret="deployment-bypass-key" --render="errors::503"
   ```
3. **Deploy Files:**
   - Rsync or Git pull latest code into `backend/`.
   - Upload updated `dist/` files to `public_html/`.
4. **Apply Database Changes & Clear Caches:**
   ```bash
   php artisan migrate --force
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```
5. **Reopen Application:**
   ```bash
   php artisan up
   ```

---

## 11. Rollback Strategy

If a critical failure occurs during deployment:

1. **Code Rollback:**
   - Revert Git commit or restore previous file backup in `backend/` and `public_html/`.
2. **Database Migration Rollback:**
   - Rollback the most recent migration batch:
     ```bash
     php artisan migrate:rollback --step=1
     ```
3. **Database Full Restore (in case of data corruption):**
   - Locate latest encrypted backup in `storage/app/backups/`.
   - Restore using:
     ```bash
     php artisan app:restore-db storage/app/backups/backup-YYYY-MM-DD-HHMMSS.sql.gz.enc
     ```
4. **Rebuild Caches:**
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   php artisan up
   ```
