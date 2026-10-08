# Phase 1 — Authentication, Layout, Audit Log

**Goal:** Secure login for 1–2 admins, the RTL application shell, and an audit trail used by every later phase.
**Depends on:** Phase 0.

## Backend
- Tables: `users` (name, email unique, password, is_active, last_login_at), `activity_logs` (user_id nullable, action, subject_type, subject_id, properties json, ip, user_agent, created_at; index on action + created_at).
- No public registration. Seeder creates admin users from `.env` (`ADMIN_1_EMAIL`, `ADMIN_1_PASSWORD`, optional second).
- Endpoints: `GET /sanctum/csrf-cookie`, `POST /api/v1/auth/login` (throttle 5/min per email+IP), `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`, `PATCH /api/v1/auth/password` (requires current password).
- Inactive users cannot log in. Regenerate session on login.
- `Audit::log($action, $subject = null, array $properties = [])` service (queued-safe, never throws). Log: login, logout, failed login, password change.
- `GET /api/v1/activity-logs` (paginated, filters: user, action, date range).

## Frontend
- Login page (react-hook-form; Arabic validation messages; server errors mapped to fields).
- `useAuthStore`: bootstrap by calling `/auth/me` on app load with a full-screen loader; `ProtectedRoute` and `GuestRoute`.
- App shell: RTL sidebar (collapsible on mobile), top bar with user menu (تغيير كلمة المرور / تسجيل الخروج), breadcrumbs, toast system, reusable `PageHeader`, `EmptyState`, `ConfirmDialog`, `DataTable` wrapper (server-side pagination/sort props) — reused by all later phases.
- Sidebar entries (placeholder pages): السائقون، الاستيراد، الأسئلة، أوامر الشغل، التقارير، الإعدادات، سجل النشاط.
- Activity log page with filters and pagination.
- Change-password page/dialog.

## Tests
- Backend: login success/failure, throttle, inactive user blocked, logout invalidates session, `me` requires auth, password change validation, audit rows written for each event, activity-logs filters.
- Frontend: login form validation and error mapping, ProtectedRoute redirect, auth bootstrap states, sidebar renders RTL.

## Definition of Done
- [ ] Log in/out works end-to-end with cookies locally.
- [ ] All later placeholder routes exist behind auth.
- [ ] Reusable `DataTable`, `ConfirmDialog`, `PageHeader` exist and are tested.
- [ ] All tests green.

---

## Project Context (identical in every phase file — read fully before starting)

**Product:** Internal web system for a transport company to recruit drivers and dispatch work orders through WhatsApp. Driver phone numbers are collected manually from WhatsApp groups into an Excel sheet (column A: phone, column B: source group name) and uploaded to the system.

**Core flow:** Upload Excel → clean/classify numbers → invite new numbers on WhatsApp (opt-in) → ask configurable questions (answers can be used as filters) → driver becomes `active` → admin creates a Work Order (N trips + M backup + filters) → system messages matching drivers → first N acceptances are `confirmed`, next M are `backup`, everyone else is told the order is filled → admin follows up manually (promote backups, record outcomes).

**Stack (do not add libraries outside this list without recording the reason in `docs/decisions.md`):**
- Frontend: React (Vite), React Router, Tailwind CSS, HeroUI v2, axios, zustand, react-hook-form
- Backend: **Laravel 13 on PHP ≥ 8.3** (already installed in Phase 0 — never downgrade; Laravel 11 is out of security support), Laravel Sanctum 4 (SPA cookie auth)
- Excel: `maatwebsite/excel`; PDF: `mpdf/mpdf` (best Arabic/RTL support); phones: `giggsey/libphonenumber-for-php`
- Tests: Pest 4 / PHPUnit 12 (backend), Vitest + React Testing Library + MSW (frontend), Playwright (E2E, Phase 8)

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