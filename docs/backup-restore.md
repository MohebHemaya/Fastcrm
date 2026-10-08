# Database Backup and Restore Procedures

This document provides operational guidelines for automated database backups, off-site storage, encryption, and verified step-by-step restoration procedures for the Driver Management System.

---

> [!WARNING]
> ### Critical Notice: Same-Host Backups Alone Are NOT a Real Backup!
> Storing backups solely on the same physical host or VPS/shared hosting server (`storage/app/backups/`) does **not** protect against disk corruption, datacenter failure, malware, account suspension, or inadvertent webroot deletion.
> 
> A robust disaster recovery strategy requires:
> 1. Replicating backups to a **secondary off-host destination** (an external storage disk outside webroot, an S3-compatible bucket, or remote cloud storage).
> 2. Routinely downloading copies off-site via the authenticated Admin Health endpoint.
> 3. Enabling **encryption at rest** for all exported database dumps.

---

## 1. Overview & Architecture

- **Command:** `php artisan app:backup-db`
- **Primary Directory:** `backend/storage/app/backups/`
- **Protection:**
  - Backups live strictly outside the web root (`public_html`).
  - Directory is shielded with `.gitignore` and `.htaccess` (`Deny from all`).
- **Export Strategies:**
  1. **Primary (`mysqldump`):** If the `mysqldump` binary is available in the server's PATH, it creates a fast, transactional single-transaction snapshot (`--single-transaction --quick --skip-lock-tables --default-character-set=utf8mb4`).
  2. **Fallback (`php_native`):** If running on Hostinger shared hosting where `mysqldump` is disabled or inaccessible, the command automatically falls back to an internal PDO-based SQL exporter. It dumps full table DDL (`CREATE TABLE`) and data chunks with disabled foreign keys (`SET FOREIGN_KEY_CHECKS=0`) inside an explicit transaction.
- **Retention:**
  - Automatically maintains the most recent $N$ backups (default: 7) and prunes older `.sql` and `.sql.enc` files.

---

## 2. Secondary Destination & Off-Site Replication

You can configure an optional secondary destination disk via Laravel's Filesystem configuration (`config/filesystems.php`).

### Configuration

In `.env`:
```env
# Optional secondary destination disk (null by default)
# Examples: 's3', 'backup_external', 'google'
BACKUP_SECONDARY_DISK=s3
```

When configured, `app:backup-db` automatically streams a copy of each completed backup to the secondary disk under `backups/`.

You can also specify a secondary disk on-demand via the CLI:
```bash
php artisan app:backup-db --secondary-disk=s3
```

---

## 3. Backup Encryption (APP_KEY)

Backups can be encrypted at rest using the application's master key (`APP_KEY`) via Laravel's authenticated AES-256-CBC cipher.

### Configuration

In `.env`:
```env
# Disabled by default
BACKUP_ENCRYPTION_ENABLED=true
```

Or via CLI flag:
```bash
php artisan app:backup-db --encrypt
```

When enabled, the plain `.sql` file is encrypted to `.sql.enc`, and the unencrypted file is immediately removed from disk.

### Decrypting an Encrypted Backup

To decrypt a `.sql.enc` file for restoration:

```bash
php artisan tinker --execute="file_put_contents(storage_path('app/backups/restore.sql'), Crypt::decrypt(file_get_contents(storage_path('app/backups/backup_2026_10_02_120000.sql.enc'))));"
```

---

## 4. Downloading Backups via Admin Health Page (Authenticated API)

Administrators can download the latest database backup directly from the admin dashboard or via an authenticated HTTP request.

- **Endpoint:** `GET /api/v1/admin/health/backups/latest`
- **Authentication:** Bearer token (`auth:sanctum`), Admin role required.
- **Response:** Binary file download with `Content-Type: application/octet-stream` and `Content-Disposition: attachment; filename="backup_YYYY_MM_DD_HHMMSS.sql[.enc]"`.

### Example Download via cURL:

```bash
curl -H "Authorization: Bearer <ADMIN_SANCTUM_TOKEN>" \
     -H "Accept: application/octet-stream" \
     -OJ \
     https://crm.yourdomain.com/api/v1/admin/health/backups/latest
```

---

## 5. Automated Scheduling

The backup command is scheduled in `backend/routes/console.php` to run once per day:

```php
Schedule::command('app:backup-db --keep=7')->daily();
```

---

## 6. Tested Restore Procedures

### Method A: Hostinger hPanel / phpMyAdmin (GUI)

1. Log in to Hostinger hPanel.
2. Navigate to **Databases** $\rightarrow$ **phpMyAdmin** for your production database.
3. Download the latest backup from the admin Health page or `storage/app/backups/`.
4. If encrypted, decrypt the file first using the command in Section 3.
5. In phpMyAdmin, click the **Import** tab.
6. Select the `.sql` backup file and click **Import**.
7. Verify table counts and row data.

---

### Method B: Command Line (SSH / Terminal)

If SSH access is enabled:

```bash
# 1. Navigate to backend directory
cd /path/to/fast-crm/backend

# 2. Put application into maintenance mode
php artisan down --message="جاري استعادة النسخة الاحتياطية لقاعدة البيانات"

# 3. Decrypt if encrypted (.sql.enc)
php artisan tinker --execute="file_put_contents('restore.sql', Crypt::decrypt(file_get_contents('storage/app/backups/backup_latest.sql.enc')));"

# 4. Restore using the mysql client
mysql -h 127.0.0.1 -u DB_USERNAME -p DB_DATABASE < restore.sql
rm restore.sql

# 5. Clear application cache and restart workers
php artisan cache:clear
php artisan config:cache

# 6. Bring application back online
php artisan up
```

---

### Method C: Automated Artisan / Tinker Restore (Verified in Tests)

If MySQL CLI is not available in the shell:

```bash
php artisan tinker --execute="DB::unprepared(file_get_contents(storage_path('app/backups/restore.sql')));"
```

---

## 7. Verification Checklist After Restoration

1. Verify that all 30 database tables are populated:
   ```bash
   php artisan tinker --execute="echo 'Tables count: ' . count(DB::select('SHOW TABLES'));"
   ```
2. Verify integrity of driver records:
   ```bash
   php artisan tinker --execute="echo 'Drivers count: ' . App\Models\Driver::count();"
   ```
3. Verify integrity of user accounts:
   ```bash
   php artisan tinker --execute="echo 'Users count: ' . App\Models\User::count();"
   ```
4. Verify application health:
   ```bash
   curl -I https://crm.yourdomain.com/api/v1/health
   ```
