# Phase 6 End-to-End Follow-up & Metrics Simulation Scenario

## Overview
This document specifies and documents the end-to-end follow-up, promotion, lifecycle, and metrics calculation scenario for Phase 6 (Work Order Follow-up, Promotions, Outcomes & Driver Performance Metrics). The complete scenario is automated and asserted in `tests/Feature/EndToEndPhase6FollowUpSimulationTest.php` against the MySQL test database.

---

## Scenario Parameters

- **Target Population:** 25 active drivers (`DriverStatus::ACTIVE`).
- **Work Order Specifications:**
  - Code: `WO-P6-XXXXXX`
  - From / To: مستودعات السادس من أكتوبر → ميناء الإسكندرية
  - Scheduled At: `now() + 2 days`
  - Cargo Type: بضائع مجمدة
  - Pay Amount: 1,800.00 ج.م
  - Trips Needed: **10**
  - Backup Needed: **5**
  - Send Strategy: **all** (immediate dispatch to 25 matched active drivers)

---

## Execution Flow & Lifecycle Steps

### Step 1: Initial Order Dispatch & Complete Filling (10 + 5)
1. Work order created in `draft` with 10 trips needed and 5 backup needed.
2. `WorkOrderService::send` dispatches `order_offer` WhatsApp messages to 25 drivers.
3. Drivers sequentially accept offers:
   - First 10 acceptances claim primary seats (`confirmed`, sequence numbers `1..10`).
   - Next 5 acceptances claim standby seats (`backup`, sequence numbers `1..5`).
   - Remaining 10 drivers receive `order_filled` notice (`too_late`).
4. **State:** Order enters `filled` with `confirmed_count = 10`, `backup_count = 5`, and `has_open_slots = false`.

### Step 2: Driver Cancellation & Open Slots (Zero Automatic Promotion)
1. Confirmed driver #3 (sequence #3) reports a breakdown (`عطل مفاجئ في مبرد السيارة`).
2. Admin cancels the driver assignment via `WorkOrderFollowUpService::removeConfirmedDriver`:
   - Work order is locked using `lockForUpdate()`.
   - `confirmed_count` is decremented from 10 to 9.
   - Recipient status transitions to `cancelled` with `cancellation_reason` and `cancelled_at`.
   - Remaining confirmed drivers are re-sequenced gaplessly (`1..9`).
   - **Crucial Rule:** Order status **remains `filled`** and **NO backup is auto-promoted**.
   - `order.has_open_slots` becomes `true` (`open_slots_count = 1`).
   - Timeline action logged: `confirmed_removed`.

### Step 3: Admin Manual Backup Promotion
1. Admin reviews standby drivers and manually selects backup #1 (lowest sequence number).
2. Admin executes `WorkOrderFollowUpService::promoteBackup`:
   - Work order and candidate recipient are locked under transaction.
   - Driver eligibility is verified (not `opted_out`, `blocked`, or `suspended`).
   - Backup driver status transitions to `confirmed` with assigned sequence #10.
   - `confirmed_count` increments back to 10; `backup_count` decrements to 4.
   - Remaining backup drivers are re-sequenced gaplessly (`1..4`).
   - Post-commit hook dispatches `backup_promoted` WhatsApp template with trip details.
   - `order.has_open_slots` returns to `false`.
   - Timeline action logged: `backup_promoted`.

### Step 4: Advancing Order Lifecycle
1. Admin advances order from `filled` to `in_progress` upon trip departure:
   - Status updated to `in_progress`.
   - Timeline action logged: `status_in_progress`.
2. Admin advances order from `in_progress` to `completed` upon cargo delivery:
   - Status updated to `completed`.
   - Timeline action logged: `status_completed`.

### Step 5: Recording Outcomes & Performance Metrics
Admin submits attendance, ratings, and operational notes for all 10 confirmed drivers:

| Driver | Sequence | Outcome | Rating | Notes | Resulting Metric Impact |
| :--- | :---: | :---: | :---: | :--- | :--- |
| Driver 1–7 | #1–#7 | `attended` | 5 ⭐ | حضور متميز والتزام بالميعاد | `trips_completed` +1, `avg_rating = 5.0`, `ratings_count = 1` |
| Driver 8 | #8 | `late` | 4 ⭐ | تأخر 20 دقيقة عن موعد التحميل | `trips_completed` +1, `late_count = 1`, `avg_rating = 4.0` |
| Driver 9 | #9 | `no_show` | null | لم يحضر ولم يرد على الاتصال | `no_shows = 1`, `trips_completed = 0` |
| Driver 10 | #10 | `cancelled_late` | null | اعتذر قبل موعد الرحلة بساعة | `cancellations = 1`, `trips_completed = 0` |

- `OutcomeRecordedEvent` fires and `UpdateDriverMetricsListener` automatically aggregates driver statistics into `driver_metrics`.
- Timeline action logged: `outcome_recorded`.

---

## Verifications & Assertions

1. **Audit & Event Trail:**
   All operations recorded in `work_order_events` with actor ID, timestamp, and metadata diffs:
   - `confirmed_removed`
   - `backup_promoted`
   - `status_in_progress`
   - `status_completed`
   - `outcome_recorded`

2. **Metrics Consistency:**
   - Drivers with `attended` or `late` have incremented `trips_completed` and updated `last_trip_at`.
   - Drivers with `no_show` or `cancelled_late` do NOT have `trips_completed` incremented.
   - Ratings are strictly averaged across valid 1–5 entries (`attended` and `late` only).

3. **Concurrency & Deadlock Resilience:**
   - Multi-process race tests verified 20 consecutive runs of concurrent backup promotions into 1 free slot (100% mutual exclusion, exactly 1 succeeds, no phantom slots).

---

## Automated Test Command

```bash
php artisan test --filter=EndToEndPhase6FollowUpSimulationTest
```
