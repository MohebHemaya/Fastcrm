# Local Development Setup Guide (XAMPP / MariaDB)

This guide walks through configuring and running the **Driver Management System** locally on Windows with XAMPP.

---

## 1. Prerequisites

1. **PHP:** CLI PHP >= 8.3 (required for Laravel 13) with the following extensions enabled in `php.ini`:
   - `pdo_mysql`, `mbstring`, `intl`, `zip`, `gd`, `fileinfo`, `openssl`, `curl`
2. **Composer:** Version >= 2.x
3. **Node.js:** Node >= 20.x, npm >= 10.x
4. **XAMPP:** MySQL (MariaDB 10.4.32 or newer)

---

## 2. Database Setup (MySQL / MariaDB)

1. Start **MySQL** from the XAMPP Control Panel.
2. Open phpMyAdmin or your MySQL CLI (`C:\xampp\mysql\bin\mysql.exe -u root`) and execute:

```sql
CREATE DATABASE IF NOT EXISTS driver_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS driver_system_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

> **Important:** SQLite is strictly forbidden across this project, even for automated testing. All unit and feature tests run against `driver_system_test` to validate real InnoDB row-level locking and concurrency.

---

## 3. Backend Setup (`/backend`)

1. Navigate to the backend directory:
   ```bash
   cd backend
   ```
2. Copy environment file and configure keys:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
3. Verify the following `.env` settings:
   ```env
   APP_TIMEZONE=Africa/Cairo
   APP_LOCALE=ar
   APP_FALLBACK_LOCALE=ar

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=driver_system
   DB_USERNAME=root
   DB_PASSWORD=

   QUEUE_CONNECTION=database
   CACHE_STORE=database
   SESSION_DRIVER=database
   SESSION_DOMAIN=localhost
   SANCTUM_STATEFUL_DOMAINS=localhost:5173,127.0.0.1:5173
   CORS_ALLOWED_ORIGINS=http://localhost:5173,http://127.0.0.1:5173
   ```
4. Run migrations:
   ```bash
   php artisan migrate
   ```
5. Run automated tests (uses `driver_system_test`):
   ```bash
   php vendor/bin/pest
   ```
6. Run static analysis:
   ```bash
   php vendor/bin/phpstan analyse --memory-limit=512M
   ```

---

## 4. Frontend Setup (`/frontend`)

1. Navigate to the frontend directory:
   ```bash
   cd frontend
   npm install
   ```
2. Run frontend unit tests:
   ```bash
   npm run test
   ```
3. Build for production:
   ```bash
   npm run build
   ```

---

## 5. Running the Application Locally

Apache is **not required**. Run the two development servers side-by-side:

### Terminal 1: Backend Server
```bash
cd backend
php artisan serve --port=8000
```

### Terminal 2: Frontend Vite Server
```bash
cd frontend
npm run dev
```
Open your browser at `http://localhost:5173`. The Vite development server automatically proxies `/api` and `/sanctum` requests to `http://127.0.0.1:8000`, ensuring cookies and Sanctum sessions remain same-origin.

### Terminal 3: Local Scheduler & Worker Simulation
To simulate the 1-minute cron job present in production on Hostinger:
```bash
cd backend
php artisan schedule:work
```

---

## 6. Troubleshooting

- **MySQL Port Conflict:** If port 3306 is in use by another service, check via `netstat -ano | findstr 3306` or adjust `my.ini` in `C:\xampp\mysql\bin\my.ini`.
- **`SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost'`:** Verify whether a root password was set in XAMPP phpMyAdmin. If a password is set, reflect it in `.env` under `DB_PASSWORD`.
- **`max_allowed_packet`:** If large imports fail, ensure `max_allowed_packet = 64M` is configured in `C:\xampp\mysql\bin\my.ini`.
- **Missing PHP Extension:** Ensure the extension line (e.g. `extension=intl`) is uncommented in `C:\xampp\php\php.ini` without duplicate loading entries.
