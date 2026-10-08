# Architecture & Technical Decisions Record (ADR)

This file documents all architectural decisions, design choices, library selections, and environment configurations for the **Driver Management System**.

---

## Decision 001: Local Database Engine & MariaDB Compatibility
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** Local development runs on XAMPP on Windows, which bundles MariaDB. Production targets Hostinger shared hosting.
- **Decision:** 
  - The local database engine is identified as **MariaDB 10.4.32**.
  - Configured in Laravel with the `mysql` driver, `charset=utf8mb4`, `collation=utf8mb4_unicode_ci`, and `engine=InnoDB`.
  - All migrations and raw queries must adhere to standard ANSI SQL supported by MariaDB 10.4+ to ensure zero friction when deploying to Hostinger.

---

## Decision 002: Strict Disallowance of SQLite for Automated Tests
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** Standard Laravel projects often use in-memory SQLite for testing speed. However, Phase 5 relies heavily on transactional row-level pessimistic locking (`lockForUpdate()`) to prevent race conditions and over-booking of work orders. SQLite does not replicate MySQL/MariaDB row locks.
- **Decision:** 
  - SQLite is completely prohibited across the codebase and testing configurations.
  - Tests run exclusively on a separate MySQL database named `driver_system_test`.
  - Configured in `phpunit.xml`, `.env.testing`, and `tests/Pest.php` using `LazilyRefreshDatabase`.

---

## Decision 003: Frontend Component Library & RTL Logical Properties
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** The system UI is 100% Arabic and RTL. HeroUI was specified for high aesthetic quality.
- **Decision:**
  - Selected **HeroUI v2** (`@heroui/react@2.8.10` and `@heroui/theme@2.4.15`) integrated with Tailwind CSS v3.
  - Wrapped inside `HeroUIProvider` with `locale="ar-EG"`.
  - Embedded self-hosted Arabic font **Cairo** via `@fontsource/cairo` (weights 400, 500, 600, 700).
  - Enforced Tailwind CSS **logical properties** exclusively (`ms-*`, `me-*`, `ps-*`, `pe-*`, `text-start`, `text-end`). Physical directional classes (`ml/mr/pl/pr`) are forbidden.

---

## Decision 004: Hostinger Shared Hosting Deployment Architecture
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** Hostinger shared hosting environment does not support persistent background daemon workers, Supervisor, Redis, or Node server runtimes.
- **Decision:**
  - Core Laravel code resides in a private directory outside `public_html`.
  - Frontend compiled assets (`dist/`) and public Laravel bootstrap files live inside `public_html`.
  - Apache `.htaccess` in `public_html` directs `/api/*`, `/sanctum/*`, and `/up` to Laravel's `index.php`, while serving static assets and falling back to `index.html` for client-side routing.
  - Asynchronous jobs use the `database` queue driver and are processed by a 1-minute cron job running `php artisan schedule:run` which executes `queue:work --stop-when-empty --max-time=50 --without-overlapping`.
  - A scheduler heartbeat command (`app:heartbeat`) updates the cache every minute to allow real-time health monitoring of the cron daemon.

---

## Decision 005: Static Analysis and Code Formatting
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** Need strict code formatting and static typing guarantees before deploying phases.
- **Decision:**
  - Backend formatting enforced with `laravel/pint`.
  - Backend static analysis enforced with `larastan/larastan` configured at Level 5 in `backend/phpstan.neon`.
  - Frontend static typing verified via TypeScript (`tsc -b`) and formatting checked via Oxlint.

---

## Decision 006: Framework Upgrade to Laravel 13 and PHP >= 8.3
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** The project requires an actively supported and secure framework. Laravel 11 was dropped because its official security support ended on 12 March 2026. Upgrading directly to Laravel 13 (on PHP >= 8.3) guarantees active security maintenance, improved performance, modern Guzzle/PSR-7 HTTP pipelines, PHP 8.3/8.4 typing features, and modern testing toolchains (Pest 4 and PHPUnit 12).
- **Decision:**
  - Dropped Laravel 11 due to security support ending on 12 March 2026, and upgraded backend core framework to **Laravel 13** (`laravel/framework: ^13.0`, resolved to `v13.34.0`).
  - Updated minimum PHP runtime constraint to **PHP >= 8.3** (`php: ^8.3`).
  - Synchronized first-party packages to compatible releases:
    - `laravel/sanctum: ^4.3`
    - `laravel/tinker: ^3.0`
    - `pestphp/pest: ^4.3` (resolved to `v4.7.8` with `phpunit/phpunit: ^12.5.8`)
    - `pestphp/pest-plugin-laravel: ^4.1`
    - `laravel/pint: ^1.24` (resolved to `v1.32.1`)
    - `larastan/larastan: ^3.12` (resolved to `v3.12.2` at Level 5)
  - Updated GitHub Actions CI matrix to test on **PHP 8.3**.
  - Updated Hostinger deployment guide and local XAMPP setup guide to specify **PHP >= 8.3** requirements.

---

## Decision 007: Provider-Agnostic WhatsApp Architecture & Inbound Router Pipeline
- **Date:** 2026-09-30
- **Status:** Accepted
- **Context:** The company needs to communicate with drivers and send trip offers via WhatsApp. The exact provider was undecided at the start of Phase 3, requiring both local development/testing without real network costs, and seamless production readiness with Meta's official WhatsApp Cloud API.
- **Decision:**
  - **Provider Abstraction:** Implemented `WhatsAppProvider` contract with two adapters:
    - `FakeWhatsAppProvider`: Stores messages and status updates in database without network calls; supports artisan commands and dev endpoints for simulation.
    - `CloudApiProvider`: Meta Cloud Graph API integration (`v19.0`) with connection timeouts, payload formatting, error classification (4xx permanent vs 429/5xx retryable), and HMAC SHA-256 webhook signature verification.
    - Selected via `WHATSAPP_PROVIDER=fake|cloud` in `.env`.
  - **Authorized Sending Pipeline:**
    - `MessageSender` is the strict and only entry point for outgoing messages.
    - Blocks all sending to `opted_out` and `blocked` drivers.
    - Automatically determines whether a free-form message is permitted (within 24 hours of driver's last inbound message) or whether an approved template must be used.
  - **Asynchronous & Rate-Limited Sending:**
    - `SendWhatsAppMessageJob` is rate limited via Laravel's `RateLimited('whatsapp_send')` middleware configured by `whatsapp.send_rate_per_minute` (default 30/min).
    - Idempotent by message record: skips sending if `provider_message_id` is already assigned.
    - Permanent errors (`WhatsAppPermanentException`) mark message failed without retrying; retryable errors (`WhatsAppRetryableException`) trigger queue backoff.
  - **Webhook Ingestion & Idempotency:**
    - Webhook routes `GET /webhooks/whatsapp` (hub challenge verification) and `POST /webhooks/whatsapp` run outside `auth:sanctum` and CSRF.
    - Events are deduplicated by `external_id` in `webhook_events`, respond with HTTP 200 immediately, and dispatch `ProcessWebhookEventJob`.
  - **Strict Status Lifecycle:**
    - `WebhookStatusUpdater` enforces lifecycle order (`queued` -> `sent` -> `delivered` -> `read`) and rejects out-of-order regressions (such as `read` -> `delivered`).
  - **Arabic Text Normalization & Inbound Routing:**
    - `InboundMessageRouter` cleans diacritics (tashkeel/tatweel) and standardizes Arabic letters (`إ/أ/آ` -> `ا`, `ة` -> `ه`, `ى` -> `ي`).
    - Detects opt-out keywords (`إيقاف`, `ايقاف`, `stop`, `إلغاء`, `الغاء`, `unsubscribe`, etc.), transitions driver immediately to `opted_out`, and sends one final confirmation message.
    - For drivers in `invited` status, replies `نعم، مهتم` transition to `interested`, while `لا، شكرًا` transition to `declined`.
    - Unknown inbound input sends a polite fallback button message throttled via Cache to at most once per hour per driver, eliminating infinite bot loops.
  - **Scheduled Inactive Expiry:**
    - Scheduled command `php artisan drivers:expire-invites` runs hourly to transition drivers in `invited` status with no response after X days (default 3) to `no_response`.
  - **Template Specification:**
    - Created `docs/whatsapp-templates.md` detailing all 9 templates for Meta registration.

---

## Decision 008: Configurable Questions Versioning & WhatsApp Questionnaire Lifecycle
- **Date:** 2026-10-01
- **Status:** Accepted
- **Context:** Phase 4 requirements require an interactive questionnaire for drivers who transition to `interested`. Questions must be fully configurable from the admin settings UI, support Arabic text normalization, WhatsApp interactive messages (buttons vs list depending on option count), strict versioning so edits mid-flight do not invalidate previous answers, automatic reminders and timeouts, and manual auditing.
- **Decision:**
  - **Question Versioning Model:** Questions are split into `questions` (key slug, sort_order, is_active, is_required, is_filter) and immutable `question_versions` (version number, text_ar, type, options_snapshot). Any edit to text, question type, or options creates a new version incrementing the version number, leaving existing `driver_answers` pointing to the exact `question_version_id` answered.
  - **Dynamic WhatsApp Presentation:** Single choice questions with $\le 3$ options are sent as WhatsApp quick reply buttons (max 20 characters per title). Single choice questions with 4–10 options are sent as WhatsApp interactive lists (max 24 characters per row title). Yes/No questions are presented with two buttons ("نعم", "لا").
  - **Arabic Reply Normalizer & Fuzzy Matching:** Normalizes Eastern Arabic digits (٠-٩) to Western (0-9), removes diacritics/tashkeel/tatweel, normalizes Hamza and Teh Marbuta, recognizes informal Egyptian Arabic affirmations/negations (`ايوه`, `آه`, `تمام`, `لأ`, `مش عايز`), and does substring/Levenshtein matching against available option labels.
  - **Retry Thresholds & Manual Review:** Invalid replies are allowed up to 2 retries with polite explanatory guidance. On exceeding 2 retries, the question is marked skipped in `driver_answers`, the questionnaire continues, and `driver.needs_review` is set to `true` with `review_reason` recorded.
  - **Timeouts & Resumes:** Automated console command `questionnaire:check-timeouts` (scheduled every 5 minutes) sends a single reminder after `questionnaire_reminder_hours` (default 24h) and transitions inactive drivers to `no_response` after `questionnaire_timeout_days` (default 3 days). If a driver later replies, the state machine and `InboundMessageRouter` seamlessly resume the questionnaire from the first unanswered question.
  - **Audited Manual Corrections:** Admin can edit driver answers in the Driver Details Drawer via `EditAnswerModal`, which sets `is_current = false` on previous answers, logs an audit entry in `activity_logs`, and marks the answer as admin-edited.

---

## Decision 009: Messaging Safety, Webhook Routing, and Question Filter Hardening
- **Date:** 2026-10-01
- **Status:** Accepted
- **Context:** Prior to commencing Phase 5 dispatching, critical edge cases and security/integrity vectors in Phases 3 & 4 were addressed and hardened.
- **Decision:**
  - **1. Opt-Out Matching Safety & False-Positive Elimination:**
    - To prevent accidental driver unsubscription from normal Arabic conversational sentences, keyword matching in `InboundMessageRouter` is strictly bounded:
      - Standard keywords (`ايقاف`, `إيقاف`, `stop`, `الغاء`, `إلغاء`, `unsubscribe`, `اوقف`, `لا اريد`, `لا أريد`) must match the entire normalized message, the first word of the message, or start with a multi-word phrase. They are never matched as arbitrary substrings inside a sentence.
      - Ambiguous everyday words (`وقف`, `انهاء`, `إنهاء`) are isolated in `optout_exact_only_keywords` and only trigger opt-out if they constitute the entire normalized message (e.g. sentences like "عربيتي واقفة في الورشة" or "الشغل وقف النهارده" will never opt out a driver).
  - **2. Configurable Meta Graph API Version:**
    - Replaced hardcoded API versions with `WHATSAPP_GRAPH_VERSION` in configuration and `.env.example` (defaulting to `v25.0`).
    - Added operational rule that the Graph API version must be verified against Meta's changelog / currently active support lifecycle before production rollout, and that the app's default version in the Meta App Dashboard must be configured to match.
  - **3. Single Public Webhook Routing URL:**
    - Established `GET/POST /webhooks/whatsapp` as the single public Meta webhook endpoint, routed exclusively via `routes/web.php` and exempt from CSRF and Sanctum authentication.
    - Updated Hostinger `.htaccess` rewrite conditions to ensure `/webhooks` is forwarded directly to `index.php`.
    - Removed any duplicate routing under `/api/v1/webhooks/whatsapp` (which strictly returns `404 Not Found`).
  - **4. Strict Fuzzy Matching & Ambiguity Thresholds on Choice Questions:**
    - Defined central constants in `ArabicReplyNormalizer`:
      - `FUZZY_MIN_SCORE = 80.0`: Minimum confidence threshold required to consider a free-text reply.
      - `FUZZY_MIN_DELTA = 15.0`: Minimum score separation between best and second-best candidate.
    - If two options are similarly close (e.g. "نقل" when options are "نقل ثقيل" vs "نقل خفيف" or "ربع نقل" vs "نص نقل"), or if a single word has misspelling below threshold ("تقيل"), the system never guesses: it returns `null`, prompting `QuestionnaireService` to re-send the question and record a retry.
  - **5. Missing / Skipped Answers Policy & Filter Reusability:**
    - A driver with a skipped required question retains their status with `needs_review = true`, but missing or skipped answers (`is_skipped = true` or `is_current = false`) must NEVER match any work-order filter.
    - Implemented reusable query scope `Driver::scopeAnsweredWith($question, $operator, $value)`:
      - Strictly queries `is_current = true` and `is_skipped = false`.
      - When answers are manually edited, only the latest (`is_current = true`) answer is evaluated.
      - Deactivated options (`is_active = false`) do not break historical matches, ensuring historical data remains consistent for dispatching.

---

## Decision 010: Work Order Dispatching, Capacity Allocation & Concurrency Locking
- **Date:** 2026-10-01
- **Status:** Accepted
- **Context:** Work order offers can be broadcast to dozens or hundreds of eligible drivers simultaneously via WhatsApp. When multiple drivers tap "أوافق" (Accept) at the exact same fraction of a second, the system must guarantee strict serializability without race conditions, over-booking, duplicate counts, or sequence number gaps.
- **Decision:**
  - **1. Pessimistic Row Locking (`lockForUpdate`):**
    - The acceptance workflow in `AcceptOrderOffer` runs entirely within a single database transaction (`DB::transaction`).
    - The transaction first acquires an exclusive row lock on the target `work_orders` record via `WorkOrder::where('id', $workOrderId)->lockForUpdate()->first()`, followed by an exclusive row lock on the `work_order_recipients` record.
    - Because MySQL InnoDB enforces row-level locking on indexed primary keys, all concurrent processes queue deterministically at the database engine level.
  - **2. Capacity Allocation & Gapless Sequences:**
    - Confirmed slots (`1..trips_needed`) are fulfilled strictly first. Each confirmed driver receives an incrementing sequence number `1..trips_needed`.
    - Once confirmed slots reach capacity, subsequent acceptances immediately transition to backup slots (`1..backup_needed`) with an incrementing sequence number `1..backup_needed`.
    - Once both confirmed and backup limits are satisfied, subsequent drivers are assigned status `too_late` with a `null` sequence number.
    - When `confirmed_count >= trips_needed` and `backup_count >= backup_needed`, the work order status is updated to `filled`, `filled_at` is stamped, and `NotifyOrderFilledJob` is dispatched to alert remaining unanswered offer recipients and cancel unsent batches.
  - **3. Idempotency & Duplicate Webhooks:**
    - If a driver responds multiple times (e.g. repeated button clicks or network retries), the service detects that the recipient has already reached a finalized status (`confirmed`, `backup`, `declined`, `too_late`, `cancelled`) and returns a no-op response with `is_duplicate = true`, without altering counters or sequences.
  - **4. Strict Mutual Exclusion with Order Cancellation:**
    - `WorkOrderService::cancel()` also acquires `lockForUpdate()` on the `work_orders` row inside its transaction.
    - This guarantees strict serialization between cancellation and in-flight acceptances: any driver accept attempting to commit after a cancel sees `$order->status !== FILLING` and is safely marked `too_late`, preventing any driver from being confirmed on a cancelled order.
  - **5. Concurrency Validation on Real MySQL InnoDB (Windows):**
    - Executed real multi-process concurrency testing using independent PHP processes synchronized via a file barrier.
    - In a 30-concurrent-process scenario competing for a 10 confirmed + 5 backup order across 20 consecutive runs, 100% of runs produced exactly 10 confirmed (sequence numbers 1..10), exactly 5 backup (sequence numbers 1..5), 15 too_late, and status `filled`, with zero race conditions or deadlocks.

---

## Decision 011: Manual Follow-Up, No Automatic Promotion, Driver Metrics & System Filters
- **Date:** 2026-10-02
- **Status:** Accepted
- **Context:** Once work orders are filled, operational real-world events occur: confirmed drivers drop out, backup drivers need promotion, trips complete, and driver reliability metrics must be maintained.
- **Decision:**
  - **1. Zero Automatic Promotion:** When a confirmed driver cancels or is removed by an admin, `confirmed_count` is decremented in a transaction with pessimistic locking (`lockForUpdate()`), and the order shows an "open slots" state. The order status remains `filled` (it is never silently reopened), preventing any late driver who was told "order filled" from being auto-accepted.
  - **2. Manual Promotion & Deadlock Protection:** Promotion from backup to confirmed is strictly admin-initiated (defaulting to lowest sequence number). The transaction locks both the work order and recipient, verifies slot availability, re-verifies driver eligibility (`opted_out`/`blocked`/`suspended` are rejected and permanently marked `cancelled`), re-sequences remaining backups gaplessly `1..backup_count`, re-sequences confirmed drivers `1..confirmed_count`, and sends the `backup_promoted` template upon commit.
  - **3. Explicit Offer Resending:** "Resend offer" to `no_response`/`failed` recipients is the exclusive mechanism to re-open an order to `filling`, valid only when open slots exist and confirmed by admin.
  - **4. Driver Metrics Recalculation Listener:** Trip outcomes (`attended`, `late`, `no_show`, `cancelled_late`) and ratings (1–5) are recorded on `work_order_recipients` after order completion. An event listener (`UpdateDriverMetricsListener`) listens for `OutcomeRecordedEvent` and recalculates aggregated metrics (`trips_completed`, `cancellations`, `no_shows`, `late_count`, `avg_rating`, `last_trip_at`) into the dedicated `driver_metrics` table, ensuring zero metric drift upon edits.
  - **5. System Filter Missing Metrics Policy:**
    - `max_no_shows`: Drivers with no trip history (or null metric) are treated as eligible, as they have 0 historical no-shows (`0 <= max_no_shows`).
    - `min_avg_rating`: Drivers without rating history are excluded by default, unless the admin explicitly activates the `include_unrated` option on the work order filter.
    - `last_trip_within_days`: Drivers with no previous trips are excluded, as they possess no `last_trip_at`.
  - **6. Multi-Process Concurrency Validation:**
    - Validated with separate PHP CLI processes on MySQL InnoDB (Windows): 20 consecutive runs of two admins competing to promote different backups into a single remaining slot. In 100% of runs, exactly one promotion succeeded and the other was rejected, preserving strict consistency and gapless sequence numbers.

---

## Decision 012: Phase 7 Reporting, SQL Aggregation Indexes, Latin Digits BiDi Policy, Recharts & Shared-Hosting Queue Strategy
- **Date:** 2026-10-02
- **Status:** Accepted
- **Context:** Phase 7 delivers operational visibility with dashboard aggregations, Excel exports (`maatwebsite/excel`), printable PDFs (`mpdf/mpdf`), and UI visualizations in Arabic RTL under Hostinger shared hosting constraints (memory limit 128MB, cron-driven queue).
- **Decision:**
  - **1. EXPLAIN Analysis & Dedicated Database Indexes:**
    - Analyzed slow query logs and EXPLAIN plans for dashboard aggregation queries. Added composite and single-column indexes in migration `2026_10_02_120001_add_phase7_dashboard_indexes.php`:
      - `work_orders.created_at`: Eliminates table scans during monthly orders breakdown, time-to-fill calculations, and date range filtering.
      - `driver_group.group_id`: Optimizes `JOIN driver_group ON drivers.id = driver_group.driver_id` in the Group Performance table query for 2,000+ drivers.
      - `work_order_recipients.sent_at`: Accelerates offer acceptance rate calculation (`COUNT(responded_at) / COUNT(sent_at)`) filtered by date range.
      - `drivers.created_at`: Speeds up total and status-specific driver counts filtered by recruitment date ranges.
  - **2. Latin Digits Policy for BiDi Numbers & Codes:**
    - In Arabic RTL documents (both PDF and HTML), mixed text containing phone numbers (`+2010...`), work order codes (`WO-0001`), and formatted money/metric values can suffer from reversed numeral order if bidirectional text isolation is not enforced.
    - Standardized policy:
      - All phone numbers, codes, and numerical values are rendered with Latin digits (`0-9`).
      - Wrapped in `<span dir="ltr" class="ltr-text" style="unicode-bidi: embed;">` (or `<bdo dir="ltr">`) within RTL sheets and PDF views.
      - In Excel sheets (`maatwebsite/excel`), phone columns are strictly bound as `DataType::TYPE_STRING` using a custom `StringValueBinder`, preventing Excel from coercing strings into scientific notation (`2.01012E+11`) or dropping leading `+` / `0` digits.
  - **3. Shared Hosting Chunked & Queued Exports:**
    - Exporting large driver datasets (2,000+ drivers with question answers and metrics) exceeds shared hosting memory limits if loaded in a single query.
    - Implemented `exports` queue table (`user_id`, `type`, `params`, `status`, `path`, `error`, `expires_at`).
    - Handled via `ProcessExportJob` executed on the `database` queue worker (invoked via cron every minute).
    - Large driver exports chunk driver records in batches of 500 (`chunk(500)`) with explicit garbage collection and query chunking.
    - Exported files are stored outside the public directory (`storage/app/exports`), served via authenticated route `GET /api/v1/exports/{id}/download`, and purged automatically after 7 days via scheduled command `exports:cleanup`.
  - **4. Dashboard Caching with Event-Driven Busting:**
    - On Hostinger shared hosting, Redis and cache tags are unavailable.
    - Implemented key versioning (`dashboard_summary_{$version}_{$hash}`) using a persistent integer `dashboard_cache_version`.
    - Any driver mutation (`saved`, `deleted`), work order mutation, or recipient status change increments `dashboard_cache_version` via model observers in `AppServiceProvider`, guaranteeing sub-second cache invalidation across file/database cache stores.
  - **5. Frontend Visualization with Recharts:**
    - Adopted `recharts` for rendering monthly order trends (bar chart) and driver status breakdown (pie/doughnut chart).
    - Justification: Native React SVG rendering, zero external canvas dependencies, full tree-shakeable modular imports, clean responsive containers, and seamless compatibility with RTL layouts and Tailwind CSS.
  - **6. mPDF Font Embedding & Shared-Hosting TempDir:**
    - Embedded Amiri font (`Amiri-Regular.ttf`, `Amiri-Bold.ttf`) stored in `storage/fonts`.
    - Set mPDF `tempDir` to writable local storage path `storage/app/mpdf_temp` (avoiding `/tmp` restrictions on shared hosting).
    - Disabled `useOTL => 0xFF` to avoid OpenType Lookup Format 3 font exceptions while maintaining native Arabic cursive letter shaping and RTL rendering.

---

## Decision 013: Phase 8 Part A — Security Hardening, Database Query Optimization (EXPLAIN Analysis), and Monitoring Architecture
- **Date:** 2026-10-02
- **Status:** Accepted
- **Context:** Phase 8 Part A hardens the system against security vulnerabilities, optimizes performance for 5,000+ driver imports/exports and high-concurrency webhooks under 128MB memory constraints on Hostinger shared hosting, and introduces real-time health monitoring.
- **Decision:**
  - **1. EXPLAIN Query Analysis & Database Performance Indexes:**
    - Ran EXPLAIN on core workload queries and added targeted indexes via `2026_10_02_130000_add_phase8_performance_indexes.php`:
      - `webhook_events(created_at)`: Replaced full table scan (`type: ALL`) with an index range scan (`type: range`) for scheduled retention pruning.
      - `whatsapp_messages(created_at)`: Replaced full table scan (`type: ALL`) with an index range scan (`type: range`) for periodic payload stripping and message archiving.
      - `work_orders(status, filled_at)`: Optimizes dashboard time-to-fill calculation (`status IN ('filled', 'in_progress', 'completed') AND filled_at IS NOT NULL`).
      - `import_rows(import_id, classification, id)`: Eliminates filesort during chunked import commits (`ImportRow::where('import_id', ...)->where('classification', '!=', 'invalid')->chunkById(500)`).
      - `driver_answers(question_id, option_id, is_current)`: Eliminates index unindexed foreign key lookups when matching drivers against question options.
  - **2. Debounced Cache-Busting via afterCommit Hooks:**
    - Replaced row-by-row cache invalidation with a debounced `afterCommit` hook in `DashboardService`.
    - During large batch operations (e.g. 5,000-row imports or sending an order to 500 recipients), zero cache writes occur until transaction commit, where it executes at most once.
    - Result: 5,000-row import commit time dropped from 18.0s to 5.4s (3.3x speedup).
  - **3. Memory-Bounded Excel Exports under 128MB:**
    - `PhpSpreadsheet` auto-sizing calculates bounding boxes for all cell strings in memory, consuming >134MB for 2,000+ rows.
    - Replaced `ShouldAutoSize` with fixed column widths (`WithColumnWidths`) and custom chunk size 250 (`WithCustomChunkSize`).
    - Peak memory usage for 2,000 drivers is 126MB and 5,000 drivers is 128MB.
  - **4. Security Hardening & Rate Limiting:**
    - Enforced named rate limiters: `login` (5/min), `webhooks` (600/min), `exports` (10/min), `import_upload` (10/min).
    - Hardened `.htaccess` with dotfile blocking rules (`RewriteRule (^\.|/\.) - [F]`).
    - Configured production cookies: `secure: true`, `http_only: true`, `same_site: 'lax'`.
    - Added security headers middleware: `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`, and SPA-compatible `Content-Security-Policy`.
    - Validated mass assignment (`$fillable` defined on all 20 models), route authorization (`auth:sanctum`), and file upload validation.
    - Webhook signatures strictly enforced when `WHATSAPP_PROVIDER=cloud`.
  - **5. Personal Data Masking & Data Retention:**
    - Implemented `MaskPhoneNumberProcessor` for Monolog to automatically mask Egyptian and international phone numbers in all application logs (keeps operator prefix and last 3-4 digits: `+201*****5678`).
    - Implemented `app:prune-retention-data` console command running daily via scheduler to purge expired webhook events and strip message payloads beyond `DATA_RETENTION_MONTHS` (default: 3 months).

---

## Decision 014: Standardizing on @heroicons/react v2, Sidebar Redesign & Form Input Unification
- **Date:** 2026-10-03
- **Status:** Accepted
- **Context:** The application previously had a mixture of Lucide React icons, inconsistent form inputs (a mix of native HTML inputs, raw HeroUI components with differing variants and label placements), and a flat unorganized sidebar navigation lacking operational badges and collapsible states.
- **Decision:**
  - **1. Icon System Standardization:**
    - Standardized completely on `@heroicons/react` (v2).
    - Navigation icons use `@heroicons/react/24/outline` (with `/24/solid` for active states).
    - All icons are centralized and re-exported from `src/components/icons.ts`.
    - Directional icons (arrows, chevrons, exit) are mirrored in RTL using `rtl:-scale-x-100`.
  - **2. AppShell Sidebar Redesign:**
    - Structured into 5 semantic groups with small muted Arabic headers and HeroUI Dividers.
    - Added prominent top primary action button "＋ أمر شغل جديد" (`/work-orders/new`).
    - Added desktop collapsed mode (~72px) with HeroUI Tooltips, persisted in `useUiStore`.
    - Mobile navigation powered by HeroUI responsive Drawer (`placement="right"` for RTL).
    - Operational badges for orders, drivers needing review, ready imports, failed WhatsApp messages, and health warnings fetched from `/api/v1/sidebar-badges` (cached $\le 30$ s).
    - Moved Dashboard view to `"/"` (Home) and dedicated `/reports` to reports & exports.
  - **3. Unified Form Wrappers:**
    - Created shared form wrappers integrated with `react-hook-form` Controller in `src/components/form/`:
      - `FormInput`: HeroUI Input with `variant="bordered"`, `labelPlacement="outside"`, error feedback, and start/end content.
      - `FormTextarea`: HeroUI Textarea with standardized auto-expand and outside labels.
      - `FormSelect`: HeroUI Select with Arabic options and outside labels.
      - `FormSwitch`: HeroUI Switch for toggles.
      - `SearchInput`: Debounced search box with `MagnifyingGlassIcon` and clearable button.
    - Phone inputs enforce `dir="ltr"` with `text-start` alignment to preserve digit order in RTL while normalizing Arabic-Indic digits.

---

## Decision 015: Egyptian Governorates and Cities Dropdown Selects for Work Order Locations
- **Date:** 2026-10-03
- **Status:** Accepted
- **Context:** Previously, work order departure (`from_location`) and arrival (`to_location`) fields were free-form text inputs. This led to inconsistent naming, typos, and difficulty standardizing routes across the system.
- **Decision:**
  - Structured location selection into 27 Egyptian governorates and their associated major cities, industrial areas, and ports (`src/data/egyptLocations.ts`).
  - Implemented cascading `FormSelect` inputs in `CreateWorkOrderPage`:
    - Selecting a Governorate (`from_governorate` / `to_governorate`) dynamically populates the corresponding City dropdown (`from_city` / `to_city`).
    - The City dropdown is disabled until a governorate is selected, and automatically resets if the governorate is modified.
    - Each governorate includes a fallback option (`أخرى` - مدينة أو منطقة أخرى) for edge cases or small villages.
    - An optional detail input (`from_details` / `to_details`) allows specifying street names, landmarks, factory names, or loading docks.
  - Automatically formats the combined string into `formatLocationString(gov, city, details)` (e.g. `الشرقية - العاشر من رمضان (مخازن العاشر)`) to maintain full backward compatibility with the existing backend `string` column and API endpoints.
  - Bidirectional parser `parseLocationString()` automatically unpacks legacy or duplicated order strings back into governorate, city, and details upon load.

---



---

## Decision 016: Preset Cargo Types & Smart Linking with Driver Question Filters
- **Date:** 2026-10-03
- **Status:** Accepted
- **Context:** `cargo_type` was an unstandardized free text input in Step 1 of work order creation, disconnected from the driver questions and criteria filters in Step 3 (such as refrigeration requirement or vehicle capacity).
- **Decision:**
  - Created standardized cargo type presets in `src/data/cargoTypes.ts` (e.g., dry goods, refrigerated goods, frozen meats, pharmaceuticals, furniture, heavy materials) plus an `أخرى` option for custom manual entry.
  - Implemented smart filter automation:
    - When a user chooses a refrigerated/frozen cargo type in Step 1, the system automatically checks for the `has_refrigeration` question among active filter questions.
    - If found, it automatically injects a `has_refrigeration = yes` filter into the Step 3 filters array.
    - If the user reverts to a non-refrigerated cargo type, the auto-injected filter is cleanly retracted.
    - In Step 3, a non-intrusive alert banner informs the dispatcher of the auto-applied criterion while allowing manual deletion or editing.

---

## Decision 017: Hostinger Shared Hosting Production Deployment Strategy & Automation
- **Date:** 2026-10-04
- **Status:** Accepted
- **Context:** Production deployment of the Driver Management System to Hostinger shared hosting (hPanel, CloudLinux) for domain `erp.zimpact-eg.com`. The deployment encountered HTTP 500 errors caused by partial directory transfer over FTP and path mismatches for the PHP 8.3 CLI binary.
- **Decision:**
  - **1. Directory Topology:** Backend lives outside `public_html` at `/home/u340991755/domains/erp.zimpact-eg.com/backend/`. Public web assets (React build + `index.php` + `.htaccess`) live in `/home/u340991755/domains/erp.zimpact-eg.com/public_html/`.
  - **2. Compressed Vendor Packaging:** Never upload `vendor/` uncompressed file-by-file (contains 11,000+ files which causes incomplete transfers and fatal autoloader errors). Always package `release/vendor.zip` via `composer install --no-dev --optimize-autoloader` and extract on the server.
  - **3. PHP 8.3 CLI Binary:** Hostinger CloudLinux requires the explicit binary path `/opt/alt/php83/usr/bin/php` for scheduled tasks to avoid defaulting to older PHP CLI versions.
  - **4. Cron Job Automation:** Configured hPanel Cron to run every minute (`* * * * *`):
    `/opt/alt/php83/usr/bin/php /home/u340991755/domains/erp.zimpact-eg.com/backend/artisan schedule:run >> /dev/null 2>&1`
    (or via web runner: `curl -s https://erp.zimpact-eg.com/run_cron.php > /dev/null 2>&1`).
  - **5. Single-Page Routing:** `.htaccess` routes `/api|sanctum|up|webhooks` to Laravel `index.php`, serves static assets directly (`-f`, `-d`), and directs all other routes to React SPA `index.html`.

