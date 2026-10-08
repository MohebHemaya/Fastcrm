# Phase 7 — Reports, Dashboard, Excel & PDF Exports (Arabic RTL)

**Goal:** Operational visibility and printable/exportable documents in correct Arabic.
**Depends on:** Phase 6.

## Dashboard
- Cards: drivers by status, active drivers, opted-out count, orders this month by status, offer acceptance rate, average time-to-fill.
- **Group performance table:** per WhatsApp group → numbers imported, invited, interested, active, declined, opted-out, conversion %, sortable.
- Simple charts (a small chart library such as `recharts` is allowed; record in `docs/decisions.md`). Date range filter. Aggregations computed in SQL, cached ~5 minutes.

## Excel exports (`maatwebsite/excel`)
- Drivers list (respects current filters, includes groups + answers as columns + metrics), order report (order details + confirmed + backup + outcomes), group performance.
- Sheets set to **RTL**, header styling, sensible column widths, phone columns stored as **text** (no scientific notation), Arabic sheet names.
- Large exports run **queued**; `exports` table (user, type, status, path, expires_at); UI shows progress and download link; files auto-deleted after 7 days (scheduled).

## PDF exports (`mpdf/mpdf`)
- Order sheet: header (company name/logo placeholder), order details, table of confirmed drivers, table of backups, notes, print date, page numbers.
- Driver profile sheet (optional single-page).
- Embed an Arabic font (e.g. Cairo/Amiri) from `storage/fonts`; RTL direction; correct letter shaping and Arabic numerals policy (documented decision: Latin digits for phone numbers).
- Temp dir configured to a writable path on shared hosting.

## Tests
- Dashboard aggregation queries against seeded data (exact numbers).
- Excel: row counts, header labels, RTL flag set, phone remains text, filters respected.
- PDF: generation succeeds, non-empty, valid PDF header, contains expected Arabic strings when text-extracted; snapshot the HTML template.
- Export queue flow: pending → done → download → expiry cleanup.
- Frontend: dashboard renders with mocked data; export buttons state handling.

## Definition of Done
- [ ] An order PDF with Arabic names/notes renders correctly (visual check documented with a screenshot in `docs/`).
- [ ] Exports of 2,000 drivers complete within hosting limits (queued).
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
