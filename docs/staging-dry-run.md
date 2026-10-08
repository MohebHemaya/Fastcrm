# Staging Deployment Dry Run Guide

This document defines the exact step-by-step procedure and expected results for deploying the Driver Management System to a staging subdomain (e.g. `staging.your-domain.com`) on Hostinger shared hosting before final production rollout. It also contains an incident tracking table to record any discrepancies encountered and their remediations.

---

## 1. Prerequisites & Staging Subdomain Setup

1. In Hostinger hPanel > **Domains** > **Subdomains**, create `staging` (e.g., `staging.yourdomain.com`).
   - Custom document root: `public_html/staging/`
2. Create staging database and user in hPanel > **Databases** > **MySQL Databases**:
   - Database name: `u123456789_staging`
   - Database username: `u123456789_stageusr`
   - Set a strong password and save it in the team vault.
3. In hPanel > **Advanced** > **PHP Configuration**:
   - Ensure the staging subdomain uses **PHP 8.3**.
   - Confirm extensions: `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `openssl`, `curl`.

---

## 2. Step-by-Step Deployment Protocol

| Step # | Action Description | Exact Command / Procedure | Expected Result |
| :---: | :--- | :--- | :--- |
| **1** | Build Frontend Assets | In local/CI root:<br>`cd frontend && npm ci && npm run build` | `dist/` directory generated with hashed JS, CSS, and `index.html`. Zero build errors. |
| **2** | Prepare Backend Archive | In local/CI root:<br>`cd backend && composer install --no-dev --optimize-autoloader` | Production vendor autoloader generated without dev dependencies. |
| **3** | Upload Staging Backend | Upload backend files to `/home/u123456789/backend_staging/` | Private code placed outside public web directory. Permissions set: `chmod -R 775 storage bootstrap/cache`. |
| **4** | Upload Frontend Build | Upload contents of `frontend/dist/` into `/home/u123456789/public_html/staging/` | `index.html`, `assets/`, `favicon.ico` present in staging document root. |
| **5** | Configure Entry Point | Copy `backend/public/index.php` to `/home/u123456789/public_html/staging/index.php`.<br>Update paths to point to `/../backend_staging/`. | Request bootstrapping routed to staging backend. |
| **6** | Deploy Staging `.htaccess` | Upload `.htaccess` to `/home/u123456789/public_html/staging/.htaccess` with HTTPS redirect, API routing, and CSP headers. | Requests to `/api/*` routed to `index.php`; SPA routed to `index.html`. |
| **7** | Configure Staging `.env` | Copy `.env.example` to `/home/u123456789/backend_staging/.env`.<br>Set `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://staging.yourdomain.com`, DB credentials. | Configuration parameters locked; `chmod 600 .env`. |
| **8** | Generate Staging App Key | In staging backend folder:<br>`php artisan key:generate` | Key generated and saved in `.env`. Saved to password manager. |
| **9** | Execute Migrations & Seeds | In staging backend folder:<br>`php artisan migrate --force`<br>`php artisan db:seed --class=AdminUserSeeder --force` | All migration tables created in `u123456789_staging`. Initial admin user seeded. |
| **10** | Prime Laravel Caches | `php artisan config:cache`<br>`php artisan route:cache`<br>`php artisan view:cache` | Cached bootstrap configuration and routes for maximum performance. |
| **11** | Configure Staging Cron | In hPanel Cron Jobs:<br>`* * * * * cd /home/u123456789/backend_staging && <PHP_83_CLI_PATH> artisan schedule:run >> /dev/null 2>&1` | Scheduled tasks (heartbeat, queues, backups) execute every minute using verified PHP 8.3 CLI binary. |

---

## 3. Post-Deployment Verification & Testing Checklist

| Test Item | Verification Method | Expected Outcome | Status |
| :--- | :--- | :--- | :---: |
| **Health Probe** | Visit `https://staging.yourdomain.com/up` | HTTP 200 OK | [ ] |
| **HTTPS & HSTS** | Request `http://staging.yourdomain.com` in curl / browser | Redirects 301 to `https://...`; `Strict-Transport-Security` header present | [ ] |
| **CSP Compliance** | Open F12 DevTools Console; navigate all views (`/`, `/drivers`, `/orders`, `/import`, `/admin/health`) | Console is completely clean. **Zero** CSP violations or blocked scripts. | [ ] |
| **Cron Heartbeat** | Log in as admin, visit `/api/v1/admin/health` or UI Health page | `cron.status` is `healthy`, heartbeat timestamp is $< 2$ minutes ago. | [ ] |
| **Database Backup Test** | Run `php artisan app:backup-db` via SSH / artisan | Creates encrypted file in `storage/app/backups/`. Health page lists backup. | [ ] |
| **Export Security** | Attempt direct access to `https://staging.../storage/exports/drivers.xlsx` | Returns 403 or 404. Download succeeds only via authenticated API route. | [ ] |
| **Driver Import & Export** | Import 500 rows sample sheet and export back to Excel | Import finishes, dashboard updates, streaming export downloads in seconds. | [ ] |
| **WhatsApp Webhook** | Trigger webhook challenge or test message from Meta Graph API Explorer | Returns HTTP 200 and processes event. | [ ] |

---

## 4. Dry Run Issues & Remediation Log

Use this table to record any issue, unexpected behavior, or error observed during the staging deployment, along with the root cause and the fix applied.

| # | Date & Time | Component / Step | Issue Observed | Root Cause | Fix Applied & Verified | Resolved By |
| :-: | :--- | :--- | :--- | :--- | :--- | :--- |
| *e.g.* | *2026-10-02* | *Cron Setup* | *Health page showed cron overdue* | *Wrong PHP binary path in hPanel cron* | *Updated path to discovered PHP 8.3 CLI binary; heartbeat returned healthy* | *Lead Dev* |
| **1** | | | | | | |
| **2** | | | | | | |
| **3** | | | | | | |
| **4** | | | | | | |
| **5** | | | | | | |
