# Data Retention & Privacy Policy

This document outlines the data retention rules, personal data protection, and pruning procedures implemented in the Driver Management System.

---

## 1. Overview & Objectives

In accordance with privacy requirements and storage optimization for Hostinger shared hosting:
1. **Personal Identifiable Information (PII) Protection:** Phone numbers are masked in all application log channels to prevent accidental exposure of driver contact numbers in log files.
2. **Disk Footprint Management:** Raw incoming webhook payloads and outgoing message JSON bodies grow over time. Pruning these payloads after a configurable window keeps the database compact and fast.
3. **Auditability Preservation:** Message metadata (status, delivery timestamps, driver linkage, error codes) is preserved for reporting, KPIs, and operational audits.

---

## 2. Phone Number Masking in Application Logs

- **Implementation:** `App\Logging\MaskPhoneNumberProcessor` attached to all application log channels (`single`, `daily`, `stderr`, `syslog`).
- **Mechanism:** Detects phone numbers matching Egyptian (`+201...`, `01...`) and international E.164 formats in log messages and context arrays (`phone`, `to`, `from`, `recipient`, `mobile`, `driver_phone`).
- **Masking Format:** Retains the country/operator prefix and the last 3–4 digits, replacing middle digits with asterisks:
  - Example: `+201012345678` $\rightarrow$ `+201*****5678`
  - Example: `01123456789` $\rightarrow$ `011****6789`

---

## 3. Data Retention Rules & Retention Window

The retention window is controlled by the environment variable:

```env
DATA_RETENTION_MONTHS=3
```

Default is **3 months**.

### Handled Entities

| Table | Action | Rationale |
|---|---|---|
| `webhook_events` | Hard deleted if `created_at < cutoff` | Raw incoming WhatsApp events are already processed and idempotent; retained only for short-term debugging. |
| `whatsapp_messages` | Sets `payload = NULL` if `created_at < cutoff` | Large JSON payloads (template structures, button payload definitions) are stripped. The row, status, sent/delivered timestamps, and driver/order IDs are kept for reports. |
| `exports` | Hard deleted after 7 days | Temporary Excel/PDF exports cleaned up daily by `exports:cleanup`. |

---

## 4. Pruning Command (`app:prune-retention-data`)

The console command `app:prune-retention-data` automates the pruning process.

### Manual Execution

```bash
# Run with default configured retention (e.g. 3 months)
php artisan app:prune-retention-data

# Run with a custom retention period (e.g. 6 months)
php artisan app:prune-retention-data --months=6

# Dry-run simulation (inspect candidate counts without modifying database)
php artisan app:prune-retention-data --dry-run
```

### Automated Scheduling

The command is registered in `routes/console.php` and executes daily via the Laravel scheduler:

```php
Schedule::command('app:prune-retention-data')->daily();
```

---

## 5. Storage Security & Path Traversal Prevention

- **Private Exports:** All generated Excel and PDF files are stored in `storage/app/exports/` outside `public_html`.
- **Authorization:** Direct file access is blocked by web server configuration. Downloads require an active Sanctum session and ownership check (`user_id`).
- **Path Sanitization:** Filenames and paths are sanitized against directory traversal attempts (`..`).
