# OSAS Autonomous Blueprint — Phase 1: Foundation, Events, Observability

This document describes the **first execution phase** implementation: a safe, feature-flagged domain event layer and observability hooks **without** changing financial outcomes or adding automation.

## 1. What was implemented

- **Configuration** (`config/intelligent.php`): toggles for events, persistence, observability logging, internal inspection API, and failure recording.
- **Trace/correlation**: `TraceRequestMiddleware` now sets **`X-Correlation-Id`** (generated if absent) and echoes both `X-Trace-Id` and `X-Correlation-Id` on responses. Existing `X-Trace-Id` / `trace_id` behaviour is preserved.
- **`TraceContext`** helper (`app/Support/Intelligent/TraceContext.php`) for `trace_id` / `correlation_id` in the intelligent layer.
- **Persistence**: tables `domain_events` and `event_record_failures` (PostgreSQL `jsonb`).
- **Domain model**: `App\Models\DomainEvent`, `App\Models\EventRecordFailure`.
- **Contracts & events**: `App\Intelligence\Contracts\DomainEventInterface`, `AbstractDomainEvent`, and concrete events (see table below).
- **Services**: `DomainEventRecorder` (never throws to core callers), `IntelligentEventEmitter`.
- **Provider**: `IntelligentServiceProvider` registers singletons.
- **Safe emitters** after successful operations in:
  - `CustomerController::store`
  - `VehicleController::store`
  - `WorkOrderService::create` / `transition` (via `DB::afterCommit` where applicable)
  - `InvoiceService::createInvoice`
  - `PaymentService::createPayment`
  - `WalletService::topUpIndividual`, `topUpFleet`, `debitIndividualForInvoice`, `debitVehicleForInvoice`
- **Internal API**: `GET /api/v1/internal/domain-events` (filters: `company_id`, `event_name`, `aggregate_type`, `aggregate_id`, `trace_id`, `from`, `to`, `per_page`) — protected by `intelligent.internal` middleware (feature flag + admin role).

## 2. Core services intentionally left unchanged (behaviour)

The following remain the **authoritative** business implementations; only **additive** event emission was added where noted:

- Wallet balance rules, idempotency, ledger posting logic (`WalletService`, `LedgerService`).
- Payment creation and invoice total updates (`PaymentService` — same DB updates; event runs **after** the transaction commits).
- Invoice sequencing, tax, inventory deduction, ledger posting (`InvoiceService`).
- Work order transitions and locking (`WorkOrderService`).
- Inventory stock movements (`InventoryService`) — **no event** in this phase (reserved for a later safe hook).
- Subscription enforcement middleware — **not** modified.

## 3. Feature flags (environment)

| Variable | Purpose |
|----------|---------|
| `INTELLIGENT_EVENTS_ENABLED` | Master switch for emitting/recording logic in `DomainEventRecorder`. |
| `INTELLIGENT_EVENTS_PERSIST_ENABLED` | Write rows to `domain_events`. |
| `INTELLIGENT_OBSERVABILITY_ENABLED` | Structured `Log::info` for `intelligent.domain_event`. |
| `INTELLIGENT_INTERNAL_DASHBOARD_ENABLED` | Expose `GET /api/v1/internal/domain-events`. |

Defaults are **false** — existing deployments behave as before until explicitly enabled.

## 4. Migrations

- `2026_03_28_120000_create_intelligent_domain_events_tables.php` — creates `domain_events` and `event_record_failures`.

## 5. Event catalogue (Phase 1)

| event_name | Trigger point | aggregate_type | Payload summary |
|------------|---------------|------------------|-------------------|
| `CustomerCreated` | `CustomerController::store` | `customer` | `customer_id`, `customer_uuid` |
| `VehicleCreated` | `VehicleController::store` | `vehicle` | `vehicle_id`, `customer_id`, `plate_number` |
| `WorkOrderCreated` | `WorkOrderService::create` (after commit) | `work_order` | `work_order_id`, `order_number`, `status` |
| `WorkOrderStatusChanged` | `WorkOrderService::transition` (after commit) | `work_order` | `work_order_id`, `from_status`, `to_status` |
| `InvoiceCreated` | `InvoiceService::createInvoice` (after transaction) | `invoice` | `invoice_id`, `invoice_number`, `status`, `total` |
| `InvoicePaid` | `PaymentService::createPayment` (after transaction) | `invoice` | `invoice_id`, `payment_id`, `amount`, `method`, `invoice_status` |
| `WalletCredited` | `WalletService::topUpIndividual`, `topUpFleet` | `wallet_transaction` | `wallet_transaction_id`, `customer_wallet_id`, `amount`, `transaction_type` |
| `WalletDebited` | `WalletService::debitIndividualForInvoice`, `debitVehicleForInvoice` | `wallet_transaction` | `wallet_transaction_id`, `customer_wallet_id`, `amount`, `transaction_type`, `invoice_id` |

**Deferred (documented for next phases):** `InventoryReserved`, `InventoryLowDetected`, `SubscriptionExpiringDetected` — require stable, non-duplicated detection points without coupling to stock critical paths.

## 6. Failure safety strategy

- `DomainEventRecorder::record()` **catches all throwables**; core flows **never** depend on success.
- On failure: `Log::error('intelligent.domain_event.failed', ...)` and optional `event_record_failures` row (if `INTELLIGENT_RECORD_FAILURES_ENABLED` is true, default on).
- No rollback of successful wallet/invoice/payment operations on event persistence failure.

## 7. Tests

- `tests/Feature/Intelligent/DomainEventFoundationTest.php` — trace/correlation headers, feature flags, persistence, internal API access rules.

## 8. Remaining work (next phases)

- Recommendation / policy engine (read-only suggestions first).
- Safe automation with explicit approval and idempotent execution records.
- Inventory/subscription signals once observation points are defined without double-counting.

## 9. Risks / limitations

- **Duplicate narrative**: High-volume paths could generate many rows — monitor table size and add retention policies later.
- **auth() in `WorkOrderService::transition`**: `caused_by_user_id` may be null when the service is invoked outside an authenticated HTTP context; acceptable for Phase 1.
- **Internal API**: Restricted to `owner` / `manager` (`UserRole::isAdmin()`); keep disabled in production until needed.
