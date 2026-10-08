# Phase 6 — Follow-up: Manual Promotion, Cancellations, Outcomes, Ratings

**Goal:** Give the admin full manual control after assignment and build driver reliability data.
**Depends on:** Phase 5.

## Manual actions on an order (all audited, all in `WorkOrderFollowUpService`)
- **Driver cancels / admin removes a confirmed driver** (with reason) → recipient `cancelled`, `confirmed_count` decremented in a transaction; order shows "مكان شاغر" banner. **No automatic promotion.**
- **Promote backup → confirmed:** admin picks a backup (default: lowest `sequence_number`); transaction with lock; driver receives the confirmation message; slot count updated.
- Remove a backup; **add driver manually** (even if outside filters) as confirmed/backup; **resend offer** to a subset of `no_response`/`failed` drivers if slots reopen (re-opens order status to `filling` when there are free slots and admin confirms).
- Order lifecycle: `filled → in_progress → completed`, and `cancelled` (with reason; notifies confirmed + backup with an Arabic cancellation message).
- `work_order_events` timeline: every action (who/when/what) shown on the order page.

## Outcomes & ratings
- After `completed`: per-confirmed-driver outcome form — `attended | late | no_show | cancelled_late`, optional rating 1–5 and note.
- `driver_metrics` table maintained by an event listener (not computed on each request): trips_completed, cancellations, no_shows, late_count, avg_rating, last_trip_at.
- Driver list/profile: show metrics, sortable columns, trip history.
- Extend order filters with **system filters**: minimum average rating, maximum no-shows, last trip within X days (Phase 5 matcher extended, still AND logic).

## Reminders
- Setting `order_reminder_hours_before` (default off): scheduled command sends a reminder template to confirmed drivers; never duplicates (flag per recipient).

## UI
- Order page action menu with confirm dialogs and reason inputs, warning banner for open slots, promote dialog listing backups by order, outcomes form, timeline tab.

## Tests
- Cancel → count decrement → promote flow; concurrent promote clicks don't over-fill; promoting when no slot is rejected.
- Cancellation notice recipients correct; cancelled order rejects late acceptances.
- Metrics listener correctness after outcomes (including edits of an outcome); filters by rating/no-shows.
- Reminder command idempotency; timeline entries created for each action.
- Frontend: promote/cancel dialogs, outcomes form, banner logic.

## Definition of Done
- [ ] Complete manual lifecycle works on Fake provider: fill → cancel a confirmed → promote backup → complete → outcomes → metrics updated.
- [ ] All tests green.

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
