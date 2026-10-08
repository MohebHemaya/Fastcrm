# Phase 4 — Configurable Questions & WhatsApp Questionnaire

**Goal:** The admin manages questions from Settings; interested drivers answer them over WhatsApp with buttons/lists; answers are stored and can be used as filters later.
**Depends on:** Phase 3.

## Data model
- `questions`: key (unique slug), sort_order, is_active, is_required, is_filter, type (`single_choice|yes_no|number|text`), current_version_id.
- `question_versions`: question_id, version, text_ar, type, options_snapshot json, created_at. **Editing text/type/options creates a new version**; old answers stay linked to the version they answered.
- `question_options`: question_id, value (stable key), label_ar, sort_order, is_active. Removing an option = deactivate, never delete.
- `driver_answers`: driver_id, question_id, question_version_id, option_id nullable, value_text nullable, value_number nullable, is_current, answered_at (only one `is_current` per driver+question; history kept).
- `settings` key/value table with typed accessor; add keys: `questionnaire_reminder_hours`, `questionnaire_timeout_days`, `send_rate_per_minute`, `invite_batch_size` (moved from config), `invite_no_response_days`.

## Settings UI (السيتنجز)
- Questions CRUD: text, type, options editor, required toggle, **"استخدام كفلتر"** toggle, drag & drop ordering, activate/deactivate.
- Validation: `single_choice` needs 2–10 options (≤3 → WhatsApp buttons, 4–10 → list message), `yes_no` → 2 buttons, option labels ≤ WhatsApp limits (buttons 20 chars, list rows 24 chars — validate in form and API).
- **Live WhatsApp preview** of how the question appears.
- General settings form for the settings keys above.

## Questionnaire engine (`QuestionnaireService`)
- On driver → `interested`: transition to `questionnaire_in_progress`, send first unanswered **active** question (ordered).
- Inbound handling via `InboundMessageRouter` handler:
  - Button/list reply → validate against the question's options → save answer → send next question or finish.
  - `number`/`text` → free reply, validated (numbers: Arabic digits accepted and converted; range optional); invalid → polite retry, max 2 retries then mark question skipped and flag the driver for review.
  - Free text on choice questions → try `ArabicReplyNormalizer` (ايوه/أيوة/آه/تمام/نعم/اه → yes; لا/لأ/مش عايز → no; option label match ignoring diacritics/hamza variants); else re-send the question.
  - Audio/image/other → polite message asking to use the buttons.
  - Ignore replies to already-answered/old question messages safely (match by question id in button payload).
- Completion: all required questions answered → status `active`, send thank-you message (template).
- Timers (scheduled command every 5 min): reminder once after `questionnaire_reminder_hours`; after `questionnaire_timeout_days` → `no_response`.
- Resume: a driver who replies again after a timeout continues where they stopped.
- Admin action **"إعادة إرسال السؤال"** and **"طلب تحديث الإجابات"** (asks only new questions added after the driver became active).

## Driver profile
- Show answers with the question version used; edit answers manually (audited).

## Tests
- Versioning: editing creates a new version, old answers keep pointing to old version.
- Full happy-path questionnaire for 5 mixed question types with the Fake provider.
- `ArabicReplyNormalizer` table tests (30+ variants).
- Retry limits; skipped-question flag; late/duplicate button taps ignored; reminder + timeout scheduling; resume after timeout.
- Validation of options limits (buttons vs list), deactivated options not shown.
- Frontend: questions form validation, reorder, preview rendering.

## Definition of Done
- [ ] A driver can go `interested → questionnaire_in_progress → active` purely through simulated WhatsApp replies.
- [ ] Editing a question mid-flight never breaks drivers currently answering.
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
