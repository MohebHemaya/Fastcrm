# Driver Management System — Go-Live Checklist

This checklist must be strictly completed and signed off prior to launching the application into production on Hostinger shared hosting.

---

## 1. Hosting & Infrastructure Readiness

- [ ] **PHP Configuration:**
  - PHP version is **8.3** (or 8.4) in hPanel > Advanced > PHP Configuration.
  - Required extensions enabled: `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `openssl`, `curl`.
  - PHP `memory_limit` set to at least `128M` (recommended `256M`).
  - `max_execution_time` set to at least `60` seconds.
- [ ] **Directory Architecture:**
  - `backend/` is placed securely **outside** `public_html/`.
  - `public_html/index.php` correctly bootstraps `../backend/bootstrap/app.php`.
  - Directory permissions applied: `storage/` and `bootstrap/cache/` are `chmod -R 775`, `.env` is `chmod 600`.
- [ ] **Hostinger Automatic Backups:**
  - In hPanel > Files > Backups, verify that automatic daily backups are **Enabled** for both files and MySQL databases.
- [ ] **Application Backup Verification:**
  - Run `php artisan app:backup-db` manually via SSH or terminal on the hosting account.
  - Verify that the encrypted backup file (`.sql.gz.enc`) was created in `storage/app/backups/`.
  - Verify that `php artisan app:restore-db --dry-run` or inspection decrypts successfully.
- [ ] **Cron Heartbeat:**
  - Cron job configured in hPanel: `* * * * * cd /home/u123456789/backend && <PHP_83_CLI_PATH> artisan schedule:run >> /dev/null 2>&1` (verify CLI binary with `<PHP_83_CLI_PATH> -v` and `<PHP_83_CLI_PATH> artisan about`).
  - Navigate to `/admin/health` (or `GET /api/v1/admin/health`) as Super Admin and verify that `cron.status` is **healthy** and last heartbeat is within the last 2 minutes.

---

## 2. Security, SSL & Content Security Policy (CSP)

- [ ] **HTTPS Enforced & HSTS:**
  - SSL certificate is active (Let's Encrypt / Hostinger SSL).
  - Visiting `http://your-domain.com` automatically redirects to `https://your-domain.com` with a `301 Moved Permanently`.
  - Security header `Strict-Transport-Security: max-age=31536000; includeSubDomains` is present in response headers.
- [ ] **Content Security Policy (CSP):**
  - Verify that the production build on the staging subdomain runs with a completely clean browser console.
  - Open Developer Tools (F12) > Console tab; navigate through all primary routes (`/`, `/drivers`, `/orders`, `/import`, `/admin/health`).
  - Confirm **zero** CSP violations (`Content Security Policy`, `eval`, or blocked inline scripts/stylesheets).
- [ ] **Protected Assets:**
  - Confirm that driver export files cannot be accessed via direct URL (e.g. `https://your-domain.com/storage/exports/drivers.xlsx` returns `403` or `404`).
  - Confirm exports are only accessible via authenticated endpoint `GET /api/v1/drivers/export/download/{filename}`.
- [ ] **Secure Cookies & Session:**
  - Session cookie is marked `Secure`, `HttpOnly`, and `SameSite=Lax`.
  - `APP_DEBUG=false` in `.env`.
  - `APP_ENV=production` in `.env`.

---

## 3. Secrets & Recovery

| Secret Key | Verified In Production | Off-Site Backup Verified | Safe Rotation Procedure Documented |
| :--- | :---: | :---: | :--- |
| **`APP_KEY`** | [ ] | [ ] | Master key used to encrypt sessions and backup files. **Must be saved separately** in an off-site password manager (e.g., Bitwarden, 1Password) — backups cannot be recovered without it. |
| **`DB_PASSWORD`** | [ ] | [ ] | Rotated in hPanel MySQL Users, immediately updated in `.env`, followed by `php artisan config:cache`. |
| **`WHATSAPP_TOKEN`** | [ ] | [ ] | Generate replacement System User Token in Meta Business Manager, update `.env`, `php artisan config:cache`, test outbound message, then revoke old token. |
| **`WHATSAPP_APP_SECRET`**| [ ] | [ ] | Update in Meta Developer Dashboard and sync into `.env` + `php artisan config:cache`. |
| **`WHATSAPP_VERIFY_TOKEN`**| [ ] | [ ] | Generate random 32-char string, update `.env` + `config:cache`, update Webhook config in Meta Dashboard. |
| **Admin Passwords** | [ ] | [ ] | Initial seed passwords changed to high-entropy unique credentials; stored in company vault. |

---

## 4. WhatsApp Business Platform Verification

- [ ] **Webhook Endpoint:**
  - Meta Webhook callback URL configured as: `https://your-domain.com/webhooks/whatsapp`.
  - Verification challenge succeeds with HTTP 200 and correct `hub.challenge`.
- [ ] **Webhook Subscriptions:**
  - Subscribed to `messages` and `message_deliveries` events.
- [ ] **Message Templates:**
  - Approved WhatsApp templates (invitations, questionnaires, work order offers, cancellations, reminders) match template names configured in `config/whatsapp.php`.
- [ ] **Simulated & Live Flow Test:**
  - Send a test invitation to a real internal phone number.
  - Verify receipt on phone, reply with Arabic confirmation, and verify status update in CRM.

---

## 5. Application Baseline & Performance

- [ ] **Database Migrations:**
  - `php artisan migrate --force` executed with zero errors.
- [ ] **Admin Seeder:**
  - Default administrative account seeded, verified, and temporary initial password immediately changed.
- [ ] **Caches Primed:**
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`
- [ ] **Excel Large Export Benchmark:**
  - Tested exporting 2,000+ drivers via `StreamingDriversExport`; verify download completes promptly and memory limit of 128M is not exceeded.
- [ ] **Rate Limiting:**
  - Login rate limiting active (5 attempts per minute).
  - Webhooks rate limit active (1,200 requests per minute).

---

## 6. Sign-off

| Role | Name | Signature / Approval | Date |
| :--- | :--- | :--- | :--- |
| **Lead Developer** | | | |
| **QA / Tester** | | | |
| **Product Owner** | | | |
| **System Administrator** | | | |
