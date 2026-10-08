# Phase 5 — Work Orders, Filters, Sending & First-N Acceptance

**Goal:** Create work orders with filters, message matching drivers, and assign the first N acceptances as `confirmed` and the next M as `backup` — **without ever exceeding capacity, even under concurrent replies**.
**Depends on:** Phase 4.

## Data model
- `work_orders`: code (unique, `WO-0001` sequence), from_location, to_location, scheduled_at, cargo_type, pay_amount decimal nullable, notes, trips_needed, backup_needed, status (`draft|sending|filling|filled|in_progress|completed|cancelled`), confirmed_count, backup_count, sent_count, filled_at, created_by, timestamps.
- `work_order_filters`: work_order_id, question_id, operator (`in|eq|gte|lte`), value json. Only questions with `is_filter = true` are allowed.
- `work_order_recipients`: work_order_id, driver_id, status (`pending|sent|confirmed|backup|declined|too_late|no_response|failed|cancelled`), sequence_number nullable, sent_at, responded_at; **unique (work_order_id, driver_id)**; indexes on (work_order_id, status).

## Matching (`DriverMatcher`)
- Eligible drivers: status `active` only (never `opted_out/blocked/suspended`), plus filters: **AND between questions, OR between selected options of the same question**, numeric ranges via `gte/lte`.
- Optional flag "exclude drivers already confirmed on another order at the same date".
- `POST /work-orders/match-preview` returns matching count + first 20 drivers; must be fast (single indexed query, no N+1) on 2,000+ drivers.

## Create flow (multi-step form, react-hook-form)
1. Details (من/إلى، التاريخ والوقت، نوع الحمولة، الأجر، ملاحظات)
2. Trips needed + backup needed
3. Filters with **live matching count**
4. Send strategy: all matched at once, or **staged** (batch size + interval) — staged stops automatically when the order is filled
5. Review → save as draft or send

## Sending
- Status `sending`: create recipient rows, dispatch queued messages via `MessageSender` (template with order details + buttons **"أوافق" / "أعتذر"**, button payload contains `order_id` + `driver_id`).
- When the first batch is dispatched the order becomes `filling`.

## Acceptance — critical logic (`AcceptOrderOffer` service)
Inside **one DB transaction with `lockForUpdate()` on the work_orders row**:
1. If order not in `filling` → recipient becomes `too_late` (or cancelled notice) and stop.
2. If recipient already answered → **idempotent no-op** (double taps, duplicate webhooks).
3. If `confirmed_count < trips_needed` → `confirmed`, `sequence_number` = next value, increment counter.
4. Else if `backup_count < backup_needed` → `backup`, `sequence_number`, increment counter.
5. Else → `too_late`.
6. If both counters reach capacity → order `filled`, `filled_at`, dispatch `NotifyOrderFilled` job.
After commit (not inside): send the appropriate message — confirmation with full details (confirmed), "تم تسجيلك احتياطي" (backup), "تم اكتمال العدد" (too_late).
- `NotifyOrderFilled`: politely informs every recipient still `sent` (no answer) that the order is filled → `too_late`; cancels remaining staged batches.
- Decline → `declined`, order stays open.

## Admin UI
- Orders list (filters: status, date, code search), order detail with tabs **مؤكدون / احتياطي / معتذرون / لم يردوا / متأخرون**, live counters via polling every 10s, retry for failed sends, cancel order (notifies confirmed + backup; Phase 6 refines).
- Order duplication ("نسخ أمر سابق").

## Tests
- Matcher: AND/OR logic, ranges, exclusion of ineligible statuses, date-conflict flag.
- **Race-condition test (mandatory, run on the local XAMPP MySQL test database — InnoDB, real row locks; SQLite is not acceptable):** 30 concurrent acceptances (separate processes or parallel DB connections) on a 10+5 order → **exactly 10 confirmed, 5 backup, 15 too_late**, sequence numbers unique and gapless, order `filled`, regardless of arrival order.
- Idempotency: same accept twice, duplicated webhook; acceptance after `filled`/`cancelled`.
- Staged sending stops once filled; failed sends don't consume capacity.
- Frontend: multi-step form validation, live match count, detail tabs, polling updates.

## Definition of Done
- [ ] Race-condition test passes repeatedly (run 20×).
- [ ] Full simulated scenario: 50 recipients, 10+5 order → correct final states and messages.
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
