# Phase 3 — WhatsApp Integration, Invitations, Opt-in / Opt-out

**Goal:** A provider-agnostic messaging layer with reliable queued sending, webhooks, and the driver invitation flow.
**Depends on:** Phase 2. **Decision needed before starting:** which provider will be used in production (build both adapters, select via `WHATSAPP_PROVIDER=fake|cloud`).

## Provider abstraction
`WhatsAppProvider` interface with DTOs:
- `sendTemplate(to, name, lang, params, buttons?)`, `sendText(to, body)`, `sendButtons(to, body, buttons[≤3])`, `sendList(to, body, buttonLabel, rows[≤10])`
- `verifyWebhook(Request)`, `parseWebhook(Request): InboundEvent[]` where events are normalized: `InboundMessage(from, type text|button|list|audio|image|other, text, payloadId, providerMessageId, timestamp)` and `StatusUpdate(providerMessageId, status sent|delivered|read|failed, errorCode?)`.
- **FakeWhatsAppProvider:** stores outgoing in DB, no network; plus a local-only artisan command / dev route to **simulate inbound messages and status updates**. Used in all tests.
- **CloudApiProvider (Meta):** implemented against config (`WHATSAPP_TOKEN`, `PHONE_NUMBER_ID`, `APP_SECRET`, `VERIFY_TOKEN`), HTTP client with timeouts; map errors to `permanent` vs `retryable`. Keep it tested with HTTP fakes only.

## Data model
- `whatsapp_messages`: driver_id, direction (`out|in`), provider_message_id (unique nullable), type, template_name, body/payload json, status (`queued|sent|delivered|read|failed`), error_code, error_message, order_id nullable (used in Phase 5), queued_at/sent_at/delivered_at/read_at.
- `webhook_events`: provider, external_id (**unique**), payload json, processed_at — for idempotency (Meta can send duplicates).

## Sending pipeline
- `MessageSender` service is the **only** way to send: it refuses to message drivers with status `opted_out`/`blocked`, chooses **template vs free-form** (free-form only within 24h of the driver's last inbound message, else a template), writes the `whatsapp_messages` row, dispatches `SendWhatsAppMessage` job.
- Job: rate limited (configurable messages/minute), retries with backoff for retryable errors only, marks `failed` with reason on permanent errors, never duplicates a send on retry (idempotent by message row).
- Settings (config now, editable in Phase 4 settings screen): `send_rate_per_minute`, `invite_batch_size`.

## Webhook
- `GET /webhooks/whatsapp` (verification) and `POST /webhooks/whatsapp`: verify `X-Hub-Signature-256`, store raw event (dedupe), respond **200 immediately**, process in a queued job. Outside `auth:sanctum` and CSRF.
- `InboundMessageRouter`: routes by driver state to handlers (Phase 3 handles invite replies + opt-out; Phase 4/5 register more handlers). Unknown input → one polite fallback message with buttons, throttled so it cannot loop.

## Invitation flow
- Admin selects drivers with status `new` (selection or "all new") → confirm dialog showing count and estimated duration → staged sending in batches → status `invited`.
- Invite message (template, Arabic, buttons **"نعم، مهتم" / "لا، شكرًا"**): introduces the company, why they are contacted, and how to stop messages ("اكتب: إيقاف").
- Reply yes → `interested` (Phase 4 will start the questionnaire; for now send a "شكرًا، هنتواصل معاك" text). Reply no → `declined`. No reply after X days (setting) → `no_response` via scheduled command.
- **Opt-out:** any inbound text matching a keyword list (`إيقاف`, `ايقاف`, `stop`, `إلغاء`, `الغاء`, `unsubscribe`, normalized for Arabic letters/diacritics) → immediately `opted_out`, send one confirmation, never message again (enforced in `MessageSender`).

## Admin UI
- Drivers list: bulk "إرسال دعوة" action; message log per driver (chat-like view, status ticks); global messages page with counters per status and a "retry failed" action; provider mode indicator (Fake vs Cloud) visible in settings.
- Create `docs/whatsapp-templates.md`: every template to be registered with Meta (name, language `ar`, category, body with placeholders, buttons) — invitation, questionnaire completion, order offer, confirmation, backup notice, order filled, cancellation, promotion, reminder (Phases 4–6 will use them).

## Tests
- Fake provider round-trip; `MessageSender` blocks opted-out/blocked; 24h window logic (template vs text).
- Webhook: signature valid/invalid, duplicate event ignored, status updates change message status in correct order (no regression `read → delivered`).
- Job retries: retryable vs permanent errors; rate limiting respected.
- Invite flow state transitions; opt-out keyword matrix (including Arabic variants); router fallback throttling.
- CloudApiProvider with mocked HTTP (success, 4xx permanent, 5xx retryable).

## Definition of Done
- [ ] Full flow works locally on Fake provider: import → invite → simulate reply → status updated.
- [ ] No code path sends a message except via `MessageSender`.
- [ ] Templates document complete and reviewed.
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
