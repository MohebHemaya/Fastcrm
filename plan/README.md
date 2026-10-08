# Driver Management System — Phase Specs

Each `phase-N-*.md` file is **self-contained**: give the agent (Antigravity) one file at a time.

| File | Scope |
|---|---|
| phase-0-foundation.md | Repos, Laravel + Sanctum, React + HeroUI RTL, queue via cron, CI, Hostinger layout |
| phase-1-auth-layout.md | Login, layout, audit log |
| phase-2-drivers-import.md | Drivers, phone cleaning, Excel import with preview, driver state machine |
| phase-3-whatsapp.md | Provider interface, Fake + Cloud API, webhooks, queue sending, invite flow, opt-in/opt-out |
| phase-4-questions.md | Configurable questions, versioning, WhatsApp questionnaire engine |
| phase-5-work-orders.md | Work orders, filters, sending, first-N acceptance (race-safe) |
| phase-6-followup.md | Manual promotion, cancellations, outcomes, ratings, reminders |
| phase-7-reports.md | Dashboard, Excel/PDF exports (Arabic RTL) |
| phase-8-hardening-deploy.md | Security, performance, E2E, monitoring, deployment runbook, UAT |

## Suggested prompt for the agent
> Read `phase-N-....md` completely. Implement only that phase, following its Definition of Done. Write the tests listed. Run all tests. Report a summary.

## Rules
- Do not start phase N+1 before phase N's Definition of Done is met and you reviewed it.
- Phases 0–2 do **not** depend on the WhatsApp provider decision.

## Decisions / actions to start early
1. **Before Phase 3:** decide the WhatsApp provider (official Cloud API recommended; unofficial libraries need a persistent Node process and are not possible on shared hosting).
2. **Start now (takes days):** create a Meta Business account, register the number, and submit the message templates listed in Phase 3 for approval.
3. **Local database:** XAMPP MySQL (it is actually MariaDB, fully compatible) with two databases: `driver_system` and `driver_system_test`. Details in Phase 0 (`docs/local-setup-xampp.md`).
4. **Confirm Hostinger plan:** PHP version, SSH access (for `composer`/`artisan`), cron jobs, database size limits.
