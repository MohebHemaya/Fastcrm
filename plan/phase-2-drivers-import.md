# Phase 2 — Drivers & Excel Import

**Goal:** Import phone numbers from Excel with cleaning, classification, preview and a final report; manage drivers and their status machine.
**Depends on:** Phase 1.

## Data model
- `drivers`: id, phone (E.164, **unique**, indexed), name nullable, status (enum `DriverStatus`), opted_out_at nullable, notes, timestamps, soft deletes.
- `whatsapp_groups`: id, name (unique, trimmed/normalized).
- `driver_group`: driver_id, group_id, first_seen_at, import_id (unique driver+group). A driver keeps **all** groups it appeared in.
- `driver_status_histories`: driver_id, from, to, reason, changed_by nullable, created_at.
- `imports`: id, user_id, original_name, stored_path, status (`uploaded|parsing|ready_for_review|committing|completed|failed|cancelled`), counts (total, new, duplicate_in_file, existing, active, invalid), error, timestamps.
- `import_rows`: import_id, row_number, raw_phone, group_name, normalized_phone nullable, classification (`new|duplicate_in_file|existing|active|invalid`), error nullable, driver_id nullable.

## Driver state machine (enum + transition guard class)
States: `new, invited, interested, questionnaire_in_progress, active, declined, no_response, suspended, blocked, opted_out`.
Define the **allowed transitions map** in one class; every status change goes through `DriverStatusService::transition($driver, $to, $reason, $user = null)` which validates, writes history + audit log. `opted_out` and `blocked` are terminal except manual admin reset (blocked only). Later phases reuse this service.

## Phone normalization (`PhoneNormalizer`, pure and heavily tested)
- Convert Arabic-Indic/Persian digits to ASCII; strip spaces, dashes, dots, parentheses, RTL marks, zero-width chars.
- Egyptian formats → E.164: `01012345678`, `1012345678`, `201012345678`, `+201012345678`, `00201012345678`.
- Other countries accepted when valid E.164 (default region `EG`, use libphonenumber). Landlines/invalid → `invalid` with a reason code (`too_short`, `too_long`, `bad_prefix`, `not_a_number`, `empty`).

## Import flow
1. `POST /api/v1/imports` upload `.xlsx/.xls/.csv` (max 5 MB, validate mime + extension, columns: phone, group; header row optional and auto-detected). Provide `GET /api/v1/imports/template` to download a sample file.
2. Parse in a **queued job using chunked reading** (memory-safe on shared hosting) → fill `import_rows` with classification. Rules: duplicates inside the file are merged (groups combined); number already in DB → `existing`; existing with status `active` → `active` ("شغال معانا"); invalid → `invalid`.
3. Status `ready_for_review`: preview screen with 4 tabs and counts — **جديد / مكرر / غلط / شغال معانا** — searchable, paginated, downloadable error list.
4. `POST /imports/{id}/commit` → job creates new drivers (status `new`), attaches groups to new **and existing** drivers, never overwrites existing status. **No WhatsApp messages are sent in this phase.**
5. Final report page (counts + downloadable Excel of invalid rows). Cancel is possible before commit. Commit is idempotent (safe to retry).

## Drivers module
- `GET /drivers`: server-side pagination, search (phone/name), filters (status, group, imported_in), sorting.
- Driver detail drawer/page: info, groups, status history, notes (editable), placeholders for answers/messages.
- Actions: add manually, edit name/notes, change status (only allowed transitions), suspend/block/unblock with reason. All audited.
- Frontend: import wizard (upload → progress polling every 3s → preview → commit → report), drivers list with filter bar, driver drawer.

## Tests
- `PhoneNormalizer`: table-driven with **40+ cases** (formats, Arabic digits, junk chars, invalid, foreign numbers).
- Classification, in-file duplicate merging with multiple groups, existing/active detection.
- Chunked import of a **5,000-row** file finishes and memory stays bounded.
- Commit idempotency; commit never downgrades an existing driver's status.
- State machine: every allowed and forbidden transition; history rows written.
- Feature tests: upload validation, permissions (auth), drivers filters/search/sort.
- Frontend: wizard steps, preview tabs, drivers filters.

## Definition of Done
- [ ] A realistic 2,000-row sheet imports, previews and commits correctly.
- [ ] Re-importing the same sheet creates no duplicates and adds no wrong statuses.
- [ ] All transitions go through `DriverStatusService`.
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
