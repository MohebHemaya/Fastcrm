# Phase 8 — Hardening, Monitoring, E2E & Deployment

**Goal:** Production readiness on Hostinger and a repeatable release process.
**Depends on:** Phases 0–7.

## Security
- Rate limits on login, webhooks, exports, import upload. Sanctum/session cookies: `secure`, `httponly`, `samesite=lax`; HTTPS enforced; security headers (HSTS, X-Content-Type-Options, frame-ancestors, referrer policy); CSP compatible with the SPA.
- Review: mass assignment, authorization on every route, file upload validation, path traversal in downloads, webhook signature enforcement in production, no debug/stack traces in production, `.env` and `storage` not web-accessible.
- Personal data: phone numbers masked in application logs; retention command pruning `webhook_events` and old `whatsapp_messages` payloads (configurable months); backups excluded from public paths.

## Performance
- Run `EXPLAIN` on: drivers list, matcher, import commit, dashboard queries; add missing indexes (documented).
- Enable `preventLazyLoading` in CI to catch N+1; fix findings.
- Load smoke tests on Fake provider: import 5,000 rows; send an order to 500 recipients; 100 concurrent inbound webhooks.

## Monitoring & operations
- Health page in the admin UI: last scheduler heartbeat (**warning if older than 5 minutes** — meaning the hPanel cron stopped), queue backlog size, failed jobs count with retry/delete, last webhook received, provider mode.
- Daily log rotation; failed-job list page; optional email alert when heartbeat is stale or many messages fail.
- Scheduled DB backup command (`mysqldump` if available, else Laravel-based export) to a protected folder + retention; document restoring.

## E2E (Playwright, against a seeded local stack with Fake provider)
Scenario: login → upload sheet → preview → commit → send invitations → simulate yes replies → simulate questionnaire answers → create order 10+5 with filter → simulate 30 acceptances → verify counts → cancel one confirmed → promote backup → complete order → record outcomes → export Excel + PDF → logout. Plus negative paths: opt-out keyword, expired session, invalid file upload.

## Deployment runbook (`docs/deployment-hostinger.md`, final version)
Step-by-step: create DB/user; PHP version + extensions checklist (mbstring, intl, gd/zip for Excel, etc.); upload release folder from `build-release.sh`; `.env` production values; `composer install --no-dev -o` (or upload vendor if no SSH); `php artisan migrate --force`; `config:cache`, `route:cache`, `view:cache`; **`storage:link` alternative if symlinks are disabled**; folder permissions; cron job; configure webhook URL + verify token at Meta; switch `WHATSAPP_PROVIDER=cloud`; smoke-test checklist; **rollback plan** (previous release folder + DB backup restore); how to deploy an update with minimal downtime (maintenance mode).

## UAT checklist (Arabic, `docs/uat-ar.md`)
A checklist the client/admin runs manually: each core scenario with expected results and a pass/fail column.

## Definition of Done
- [ ] E2E suite green in CI.
- [ ] Health page reflects a stopped cron within 5 minutes.
- [ ] Runbook validated by a dry-run deploy to a staging subdomain.
- [ ] UAT checklist completed with no blocking issues.

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
