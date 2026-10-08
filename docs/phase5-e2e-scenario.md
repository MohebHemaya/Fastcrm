# Phase 5 End-to-End Work Order Simulation Scenario

## Overview
This document specifies and documents the end-to-end simulated scenario for Phase 5 (Work Orders & Dispatch Workflow), validated automatically via `tests/Feature/EndToEndPhase5WorkOrderSimulationTest.php` using the `FakeWhatsAppProvider`.

---

## Scenario Parameters

- **Target Population:** 50 active drivers (`DriverStatus::ACTIVE`).
- **Work Order Specifications:**
  - Code: `WO-SIM-XXXXXX`
  - From / To: مستودعات السادس من أكتوبر → ميناء دمياط
  - Scheduled At: `now() + 2 days`
  - Cargo Type: حاويات مبردة
  - Pay Amount: 2,500.00 ج.م
  - Trips Needed: **10**
  - Backup Needed: **5**
  - Send Strategy: **all** (immediate dispatch to all 50 matched drivers)

---

## Execution Steps

### 1. Population & Work Order Creation
- 50 active drivers are provisioned with valid Egyptian mobile numbers (`+2015XXXXXXXX`).
- Work order is drafted and committed with initial counts set to 0.

### 2. Dispatching Order Offers (`WorkOrderService::send`)
- `WorkOrderService::send($order)` is executed.
- **Results:**
  - Status advances from `draft` → `sending` → `filling`.
  - 50 `WorkOrderRecipient` rows are created with status `sent` (batch 1).
  - 50 outbound WhatsApp messages are queued/sent via `FakeWhatsAppProvider` with template `order_offer` and interactive payload buttons:
    - Button 1: `order_accept:{order_id}:{driver_id}` ("أوافق")
    - Button 2: `order_decline:{order_id}:{driver_id}` ("أعتذر")

### 3. Simulating Inbound Responses
All 50 drivers respond sequentially:
1. **Drivers 1 to 40 (Interactive Button Payloads):**
   ```bash
   php artisan whatsapp:simulate-inbound +2015XXXXXX --button="order_accept:{order_id}:{driver_id}"
   ```
2. **Drivers 41 to 50 (Arabic Free-Text Replies):**
   ```bash
   php artisan whatsapp:simulate-inbound +2015XXXXXX "أوافق"
   ```
   *Note: Because each driver has exactly one open offer in status `sent`, the free-text reply is accurately resolved to accept the offer without ambiguity.*

---

## Outcome & Verification

### Order State
- **Status:** `filled`
- **Confirmed Count:** 10
- **Backup Count:** 5
- **Filled At:** Set to timestamp when the 15th seat was claimed.

### Recipient Allocation & Ordering
1. **Confirmed Seats (10 drivers):**
   - Recipients 1 through 10 are marked `confirmed`.
   - Assigned continuous, gapless sequence numbers: `1, 2, 3, 4, 5, 6, 7, 8, 9, 10`.
   - Sent confirmation template: `order_confirmation`.
2. **Backup Seats (5 drivers):**
   - Recipients 11 through 15 are marked `backup`.
   - Assigned continuous, gapless sequence numbers: `1, 2, 3, 4, 5`.
   - Sent backup notice template: `order_backup_notice`.
3. **Late Responders (35 drivers):**
   - Recipients 16 through 50 are marked `too_late`.
   - Sent order filled template: `order_filled`.

### Message Log Counts
| Template Name | Direction | Count | Purpose |
| :--- | :---: | :---: | :--- |
| `order_offer` | OUT | 50 | Initial invitation to all matched drivers |
| `order_confirmation` | OUT | 10 | Assignment confirmation for primary drivers |
| `order_backup_notice` | OUT | 5 | Standby rank notification for backup drivers |
| `order_filled` | OUT | 35 | Courteous notice that quota is filled |

### Notification Reconciliation
- The scheduled maintenance command is run:
  ```bash
  php artisan work-orders:reconcile-notifications
  ```
- Result: `Reconciled 0 missing notifications.` (proves all messages were reliably dispatched at event time with zero duplicates).

---

## Automated Test
The entire scenario is executed as part of CI via:
```bash
php artisan test --filter=EndToEndPhase5WorkOrderSimulationTest
```
