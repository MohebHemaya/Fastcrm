# Driver Management System — Project Rules & Guidelines

## Core Principles & Workflow
1. **One Phase at a Time:** Implement strictly according to `plan/phase-N-*.md`. Do not start Phase N+1 before Phase N's Definition of Done is met.
2. **Code & Language:**
   - UI, user-facing error messages, and all WhatsApp messages: **Arabic only, RTL**.
   - Code, class/function names, comments, and commit messages: **English**.
   - Arabic translations centralized in `src/i18n/ar.ts` (frontend) and `lang/ar` (backend).
3. **Frontend Styling & RTL:**
   - Use Tailwind CSS with **logical properties only** (`ms-`, `me-`, `ps-`, `pe-`, `text-start`, `text-end`). **Never** use physical directional classes like `ml/mr/pl/pr`.
   - Component library: HeroUI v2 with `HeroUIProvider` configured for `ar-EG` and `<html lang="ar" dir="rtl">`.
4. **Hosting & Environment Constraints:**
   - Target production: **Hostinger shared hosting (hPanel)**.
   - No Redis, no websockets, no long-running background daemons.
   - Queues use the `database` driver, processed by a cron job every minute (`schedule:run`).
   - Backend lives outside `public_html`; frontend build (`dist/`) + backend `public/` live in `public_html`.
5. **Database Standards:**
   - **MySQL / MariaDB InnoDB** with `utf8mb4` and `utf8mb4_unicode_ci` everywhere.
   - **SQLite is strictly forbidden**, even in test environments (tests run against `driver_system_test` to support real MySQL row-level locks).
   - Proper indexes, foreign keys, snake_case table/column names, E.164 phone numbers, decimal(10,2) for money, PHP backed enums for statuses.
6. **API Conventions:**
   - Uniform JSON response envelope: `{ "data": ..., "meta": ..., "message": ... }`.
   - All protected routes behind `auth:sanctum`.
   - Validation errors return HTTP 422 with localized Arabic messages.
7. **Decisions & Auditability:**
   - Document any architectural decisions or library additions in `docs/decisions.md`.
