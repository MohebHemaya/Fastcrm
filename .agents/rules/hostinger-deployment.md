# Hostinger Deployment Memory & Production Reference

## 1. Production Environment & Paths
- **Hosting:** Hostinger Shared Hosting (CloudLinux / hPanel)
- **Domain Root:** `/home/u340991755/domains/erp.zimpact-eg.com/`
- **Backend Directory:** `/home/u340991755/domains/erp.zimpact-eg.com/backend/`
- **Public Root:** `/home/u340991755/domains/erp.zimpact-eg.com/public_html/`
- **Database:** `u340991755_Fast_CRM`
- **Database User:** `u340991755_Fast_CRM`
- **PHP Version:** PHP 8.3 (LiteSpeed web / alt-php83 CLI)
- **PHP 8.3 CLI Binary:** `/opt/alt/php83/usr/bin/php`

---

## 2. Directory Architecture
- **Backend (`domains/erp.zimpact-eg.com/backend/`):**
  - Contains `app/`, `bootstrap/`, `config/`, `database/`, `lang/`, `resources/`, `routes/`, `storage/`, `vendor/`, `artisan`, `.env`.
  - Lives **outside** `public_html` for security.
- **Frontend / Public (`domains/erp.zimpact-eg.com/public_html/`):**
  - Contains `assets/`, `index.html` (from React `frontend/dist/`), `favicon.svg`, `robots.txt`.
  - Contains `index.php` (routes requests to `../backend/vendor/autoload.php` and `../backend/bootstrap/app.php`).
  - Contains `.htaccess` with rewrite rules:
    - HTTPS enforcement.
    - `/api/*`, `/sanctum/*`, `/up`, `/webhooks/*` routed to `index.php`.
    - Static assets served directly (`-f`, `-d`).
    - All other routes routed to React SPA `index.html`.

---

## 3. Deployment & Packaging Rules
1. **Never upload `vendor/` uncompressed file-by-file:**
   - Vendor contains 11,000+ files. Web file managers and FTP drop files, causing fatal errors (e.g. missing `autoload_real.php` or `function.php`).
   - Always prepare `vendor.zip` using: `composer install --no-dev --optimize-autoloader`.
   - Upload `release/vendor.zip` (75 MB) to `backend/` and extract it on the server.
2. **Frontend Build:**
   - Run `npm run build` in `frontend/`.
   - Copy `frontend/dist/*` into `public_html/`.
3. **Database:**
   - Database credentials in `backend/.env`.
   - Initial migration/seed import via `release/database.sql` or phpMyAdmin.
   - Admin Account: `admin@erp.zimpact-eg.com` / `Saba7g@mil`.

---

## 4. Cron Job Configuration (Heartbeat & Queues)
- **Hostinger Cron Setup (every minute `* * * * *`):**
  ```bash
  /opt/alt/php83/usr/bin/php /home/u340991755/domains/erp.zimpact-eg.com/backend/artisan schedule:run >> /dev/null 2>&1
  ```
- **Alternative URL Cron:**
  ```bash
  curl -s https://erp.zimpact-eg.com/run_cron.php > /dev/null 2>&1
  ```
- Processes `queue:work` (database driver) and records `app:heartbeat` in cache every minute.
