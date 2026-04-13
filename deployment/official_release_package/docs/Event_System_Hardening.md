# Event System Hardening — Real Domain Events for Intelligence

## Objective

Operational telemetry for the Smart Command Center and Phase 2 intelligence read models comes from **`domain_events`** rows produced by **`DomainEventRecorder`** when real business operations run. This phase tightens coverage so inventory and reservation flows emit the same class of events as customers, vehicles, work orders, invoices, and payments.

## Persistence pipeline

1. **`IntelligentEventEmitter::emit(DomainEventInterface)`** — application entry.
2. **`DomainEventRecorder::record()`** — no throw; respects flags:
   - `intelligent.events.enabled` → **`INTELLIGENT_EVENTS_ENABLED`**
   - `intelligent.events.persist.enabled` → **`INTELLIGENT_EVENTS_PERSIST_ENABLED`**
3. Rows are written to **`domain_events`** with tenant **`company_id`**, optional **`branch_id`**, **`caused_by_user_id`**, **`trace_id`**, **`correlation_id`** (from request context), **`occurred_at`**.

If persistence is off, intelligence aggregates see **no new rows** (command center stays sparse).

## Schema mapping (code vs DB)

| Concept | Interface / code | `domain_events` column |
|--------|-------------------|---------------------------|
| Event type | `DomainEventInterface::name()` | **`event_name`** (e.g. `CustomerCreated`, `StockMovementRecorded`) |
| Aggregate | `aggregateType()` / `aggregateId()` | **`aggregate_type`**, **`aggregate_id`** |
| Body | `payload()` | **`payload_json`** |
| Context | `metadata()` (+ trace merged in recorder) | **`metadata_json`**, **`trace_id`** |

There is **no** separate `event_type` column; **`event_name`** is the stable discriminator for Phase 2 grouping.

## Events emitted today (operational)

| Business action | Event class | `event_name` | `aggregate_type` |
|-----------------|-------------|--------------|-------------------|
| Customer created (API) | `CustomerCreated` | `CustomerCreated` | `customer` |
| Vehicle created (API) | `VehicleCreated` | `VehicleCreated` | `vehicle` |
| Work order created | `WorkOrderCreated` | `WorkOrderCreated` | `work_order` |
| Work order status changed | `WorkOrderStatusChanged` | `WorkOrderStatusChanged` | `work_order` |
| Invoice created | `InvoiceCreated` | `InvoiceCreated` | `invoice` |
| Payment recorded (invoice payment) | `InvoicePaid` | `InvoicePaid` | `invoice` |
| Wallet credit / debit | `WalletCredited` / `WalletDebited` | same | `wallet` (existing) |
| **Stock movement created** (deduct / add / reversal via `InventoryService`) | **`StockMovementRecorded`** | `StockMovementRecorded` | `stock_movement` |
| **Reservation created** | **`InventoryReserved`** | `InventoryReserved` | `inventory_reservation` |
| **Consume reservation** (direct `StockMovement` row) | **`StockMovementRecorded`** | `StockMovementRecorded` | `stock_movement` |

**Not added as separate events** (to avoid duplicate spam with existing flows):

- **Payment** is already represented by **`InvoicePaid`** when `PaymentService::createPayment` runs (includes `payment_id` in payload). No second `PaymentRecorded` event.

**Release / cancel / expire reservation** — inventory quantity changes only; no extra domain event in this pass (optional future: `InventoryReservationReleased`).

## Required environment flags

| Flag | Purpose |
|------|---------|
| **`INTELLIGENT_EVENTS_ENABLED=true`** | Allows recorder to run (logging path; required before persist). |
| **`INTELLIGENT_EVENTS_PERSIST_ENABLED=true`** | Writes rows to **`domain_events`**. |
| **`INTELLIGENT_OBSERVABILITY_ENABLED`** (optional) | `Log::info` for each emitted event. |
| For **API / Command Center** visibility (unchanged from prior phases): internal dashboard + Phase 2 + per-feature flags, e.g. **`INTELLIGENT_INTERNAL_DASHBOARD_ENABLED`**, **`INTELLIGENT_READ_MODELS_ENABLED`** or **`INTELLIGENT_PHASE2_ENABLED`**, **`INTELLIGENT_COMMAND_CENTER_ENABLED`**, plus insights/overview/etc. as needed. |

## How to validate end-to-end

1. Set **`INTELLIGENT_EVENTS_ENABLED=true`** and **`INTELLIGENT_EVENTS_PERSIST_ENABLED=true`** in **`backend/.env`**, then restart PHP / `saas_app`.
2. Perform a real operation (e.g. create customer, post stock adjustment, reserve inventory).
3. Query DB: `SELECT id, event_name, aggregate_type, company_id, occurred_at FROM domain_events ORDER BY id DESC LIMIT 20;`
4. Enable intelligence flags and call **`GET /api/v1/internal/intelligence/insights`** — **`totals.events`** and **`by_event_name`** should reflect new names (`StockMovementRecorded`, `InventoryReserved`, etc.).

## Command Center impact

Phase 4 command center and Phase 2 insights/alerts/recommendations **read `domain_events` only**. More diverse, real **`event_name`** and **`aggregate_type`** values improve:

- **Insight counts** and breakdowns.
- **Entity reference pool** (recent aggregates in the insights window) for contextual links.
- **Explainability** inputs where rules depend on event mix (Phase 6).

No UI changes in this phase.

## Tests

See **`tests/Feature/Intelligent/DomainEventTelemetryTest.php`**: customer create → `CustomerCreated`; stock add → `StockMovementRecorded`; reserve → `InventoryReserved`; insights API sees events when Phase 2 + insights flags are on in the test.

## Remaining gaps (optional follow-up)

- Domain events for **reservation release/cancel/expire** (operational but lower signal).
- **`PaymentRecorded`** as a distinct event (currently **`InvoicePaid`** is the single source of truth per payment).
- Cross-request **deduplication** keys on `domain_events` (not implemented; rely on one emit per successful business transaction).
