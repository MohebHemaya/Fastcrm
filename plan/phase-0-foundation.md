# Phase 0 — Foundation

**Goal:** A runnable empty skeleton (backend + frontend + CI + deploy layout) that all later phases build on.

## Backend (`/backend`)
- New Laravel app; **MySQL on XAMPP** for local dev, configured via `.env`: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=driver_system`, `DB_USERNAME=root`, empty password (XAMPP default). Note: XAMPP actually ships MariaDB — fully compatible with Laravel's `mysql` driver; record the exact version in `docs/decisions.md` and avoid MySQL-8-only features unless the Hostinger version supports them.
- Database config: `charset=utf8mb4`, `collation=utf8mb4_unicode_ci`, `engine=InnoDB`, strict mode on.
- **Separate test database** `driver_system_test` (`.env.testing` + Pest/phpunit config, `RefreshDatabase`/`LazilyRefreshDatabase`). SQLite in-memory is **not allowed** — later phases rely on MySQL row locks.
- Timezone `Africa/Cairo`; locale `ar`, fallback `ar`.
- Install Sanctum for **SPA cookie auth**: configure `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN`, CORS (`supports_credentials`), `config/sanctum.php`.
- Install Pest + Laravel Pint (+ Larastan level 5 if it runs cleanly).
- API versioning: all routes under `/api/v1`. `GET /api/v1/health` returns `{status, time, db: ok|fail}`.
- Global JSON exception handling for API routes (404, 401, 403, 419, 422, 429, 500) using the envelope from the conventions, Arabic messages in `lang/ar`.
- Queue: `QUEUE_CONNECTION=database`; create jobs + failed_jobs tables. In `routes/console.php` schedule `queue:work --stop-when-empty --max-time=50` every minute with `withoutOverlapping()`.
- Scheduler heartbeat: a scheduled command writing `last_scheduler_run_at` (cache/DB) every minute (used later by monitoring).
- Base classes: `ApiController` (response helpers), base `FormRequest` with Arabic attribute names support.
- `Model::preventLazyLoading(! app()->isProduction())`.

## Frontend (`/frontend`)
- Vite + React + TypeScript; Tailwind; HeroUI v2 (`HeroUIProvider`, tailwind plugin); React Router (data router) with a root layout and a 404 route.
- **RTL:** `<html lang="ar" dir="rtl">`; HeroUI locale `ar-EG`; Arabic font self-hosted via `@fontsource` (Cairo or IBM Plex Sans Arabic). Add a short `docs/rtl-guidelines.md` (logical properties only, icon mirroring rules).
- `src/lib/api.ts`: axios instance (`baseURL: /api/v1`, `withCredentials: true`, XSRF handled), interceptors: 401 → clear auth + redirect to `/login`; 419 → refresh CSRF cookie and retry once; 422 → normalize to `{fieldErrors}`; 429/5xx → toast.
- zustand stores skeleton: `useAuthStore`, `useUiStore` (toasts).
- Folder structure: `src/features/*`, `src/components`, `src/lib`, `src/i18n/ar.ts`, `src/routes`.
- Vitest + RTL + MSW configured, with one example test.

## Local setup on XAMPP (`docs/local-setup-xampp.md`)
- Start MySQL from the XAMPP Control Panel; create `driver_system` and `driver_system_test` (utf8mb4_unicode_ci) via phpMyAdmin or a provided SQL script.
- PHP: use a CLI PHP version that satisfies Laravel's requirement (XAMPP's PHP if compatible, otherwise a separate PHP CLI on PATH); enable in `php.ini`: `pdo_mysql, mbstring, intl, zip, gd, fileinfo, openssl`.
- Apache is **not required**: run `php artisan serve` (port 8000) + `npm run dev` (port 5173); Vite dev server proxies `/api` and `/sanctum` to `http://127.0.0.1:8000` so cookies stay same-origin.
- Local cookie settings: `SESSION_DOMAIN=localhost`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173`.
- Local scheduler: document running `php artisan schedule:work` in a terminal to simulate the hPanel cron.
- Troubleshooting section: MySQL port conflict, `max_allowed_packet`, `SQLSTATE[HY000] [1045]`, missing extension.

## Deploy layout (document + scripts)
- `docs/deployment-hostinger.md` describing: Laravel outside `public_html`, `public/` contents + React `dist` inside `public_html`, `.htaccess` rules (`/api/*` → Laravel `index.php`, everything else → SPA `index.html`, force HTTPS), the exact cron command for hPanel (`* * * * * cd /path/backend && php artisan schedule:run >> /dev/null 2>&1`).
- `scripts/build-release.sh` that builds the frontend and assembles a ready-to-upload release folder.

## CI
- GitHub Actions: backend (composer install, pint --test, pest on MySQL service), frontend (npm ci, lint, typecheck, vitest, build).

## Tests
- Backend: health endpoint (ok + db failure path), migrations run cleanly on an empty MySQL database (`migrate:fresh`) with utf8mb4 Arabic text round-trip (insert/read Arabic string with emoji-safe characters), JSON 404/401 shape, scheduler registers queue command.
- Frontend: axios interceptors (401, 419 retry, 422 normalization), app renders with `dir="rtl"`.

## Definition of Done
- [ ] `php artisan serve` + `npm run dev` work together with cookie auth CORS configured, against **XAMPP MySQL**.
- [ ] `docs/local-setup-xampp.md` lets a new developer run the project from scratch; tests run against `driver_system_test`.
- [ ] All tests + lint + CI green.
- [ ] `build-release.sh` produces a folder structure matching the deployment doc.
- [ ] `docs/decisions.md` created.

---

## Project Context (identical in every phase file — read fully before starting)

**Product:** Internal web system for a transport company to recruit drivers and dispatch work orders through WhatsApp. Driver phone numbers are collected manually from WhatsApp groups into an Excel sheet (column A: phone, column B: source group name) and uploaded to the system.

**Core flow:** Upload Excel → clean/classify numbers → invite new numbers on WhatsApp (opt-in) → ask configurable questions (answers can be used as filters) → driver becomes `active` → admin creates a Work Order (N trips + M backup + filters) → system messages matching drivers → first N acceptances are `confirmed`, next M are `backup`, everyone else is told the order is filled → admin follows up manually (promote backups, record outcomes).

**Stack (do not add libraries outside this list without recording the reason in `docs/decisions.md`):**
- Frontend: React (Vite), React Router, Tailwind CSS, HeroUI v2, axios, zustand, react-hook-form
- Backend: Laravel (current stable supported by the host's PHP), Laravel Sanctum (SPA cookie auth)
- Excel: `maatwebsite/excel`; PDF: `mpdf/mpdf` (best Arabic/RTL support); phones: `giggsey/libphonenumber-for-php`
- Tests: Pest (backend), Vitest + React Testing Library + MSW (frontend), Playwright (E2E, Phase 8)

**Constraints**
- UI and all WhatsApp messages: **Arabic only, RTL**. Code, comments, commit messages: English.
- Hosting: **Hostinger shared hosting (hPanel)**. No Redis, no long-running workers, no websockets. Queue = `database` driver processed by a **cron job every minute** (`schedule:run`). Laravel lives outside `public_html`; React build + Laravel `public/` contents are served from `public_html`; API at `/api/v1`, same domain as the SPA.
- Database: **MySQL** everywhere — local development on **XAMPP**, production on Hostinger. Never use SQLite, not even for tests (tests run on a separate MySQL database). All tables: InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`.
- Scale: 2,000+ drivers, 100+ work orders, 1–2 admin users with identical permissions (no role system).
- WhatsApp provider is **not decided yet** → everything talks to a `WhatsAppProvider` interface; a `FakeWhatsAppProvider` is used for dev/tests.

**Conventions**
- API: JSON envelope `{ "data": ..., "meta": ..., "message": ... }`; validation errors HTTP 422 with Arabic messages; Form Requests + API Resources; all routes behind `auth:sanctum` except login/webhooks.
- DB: snake_case, reversible migrations, factory + seeder for every model, proper indexes and foreign keys, phones stored in E.164, money as `decimal(10,2)`, PHP backed enums for statuses.
- Frontend: feature-based folders, API calls only through a service layer (never inside components), zustand for global state only (auth, UI), react-hook-form for every form, Tailwind **logical properties** (`ms-`, `me-`, `ps-`, `pe-`, `text-start`) — never `ml/mr/pl/pr`. All Arabic strings in one place (`src/i18n/ar.ts`) and Laravel `lang/ar`.

**How to work (for the AI agent)**
1. Implement **only this phase**. Do not start or stub later phases beyond what is listed.
2. Write tests alongside the code; run the full backend + frontend suites before declaring done.
3. If something is ambiguous, pick the simplest reasonable option and record it in `docs/decisions.md` (do not stop to ask unless blocked).
4. Work in small steps; keep the app runnable at every step.
5. Finish with a summary: what was built, how to run it, how to run tests, open issues.
