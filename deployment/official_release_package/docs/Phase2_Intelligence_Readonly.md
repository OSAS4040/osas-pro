# Phase 2 — Read-only intelligence (insights, recommendations, alerts, overview)

## Purpose

Phase 2 adds an **additive**, **read-only** HTTP surface that analyzes data already stored in Phase 1’s **`domain_events`** table (and reads **`event_record_failures`** for diagnostic alerts). It does **not** change invoices, payments, wallets, stock, work orders, subscriptions, or any other operational write paths.

## Hard constraints (contract)

| Constraint | Implementation |
|------------|----------------|
| Read-only | Controllers and services use **SELECT**-style access only (`DomainEvent::query()`, `EventRecordFailure::query()`). No `insert`, `update`, `delete` in Phase 2 code paths. |
| No automation | No jobs, queues, listeners, or schedulers introduced for Phase 2. |
| No operational mutation | Phase 2 does not call POS, wallet, invoice, payment, inventory, or work-order services. |
| Additive | New routes, config keys, middleware, services, tests, and this document only. |
| Feature flags | **All off by default** (see below). |

## Configuration (`config/intelligent.php`)

### Master gate

- **`INTELLIGENT_PHASE2_ENABLED`** (default `false`)  
  When `false`, middleware returns **404** for all Phase 2 intelligence routes (even if the internal dashboard is on).

### Per-endpoint gates

Each endpoint additionally requires its own flag (default `false`):

| Env variable | Config path | Route |
|--------------|-------------|--------|
| `INTELLIGENT_PHASE2_OVERVIEW_ENABLED` | `intelligent.phase2.features.overview` | `GET /api/v1/internal/intelligence/overview` |
| `INTELLIGENT_PHASE2_INSIGHTS_ENABLED` | `intelligent.phase2.features.insights` | `GET /api/v1/internal/intelligence/insights` |
| `INTELLIGENT_PHASE2_RECOMMENDATIONS_ENABLED` | `intelligent.phase2.features.recommendations` | `GET /api/v1/internal/intelligence/recommendations` |
| `INTELLIGENT_PHASE2_ALERTS_ENABLED` | `intelligent.phase2.features.alerts` | `GET /api/v1/internal/intelligence/alerts` |

### Prerequisites

Phase 2 routes are registered with:

1. **`auth:sanctum`** (same authenticated API group as the rest of v1).
2. **`intelligent.internal`** — requires `INTELLIGENT_INTERNAL_DASHBOARD_ENABLED=true` and an **admin** user (`owner` or `manager`).
3. **`intelligent.phase2`** — requires `INTELLIGENT_PHASE2_ENABLED=true`.

If the internal dashboard is disabled, Phase 2 returns **404** before Phase 2-specific logic runs.

## HTTP API

All methods are **GET**. Responses include:

- `data` — payload (structure varies by endpoint).
- `meta.read_only` — always `true`.
- `meta.phase` — always `2`.
- `trace_id` — request trace (aligned with existing API convention).

### Query parameters (optional)

Shared across endpoints where applicable:

- **`from`** / **`to`** — date bounds for the analytics window (defaults: `to = now`, `from = to − 30 days`), applied to `domain_events.occurred_at` (and failure counts in overview where noted).
- **`company_id`** — override company filter; otherwise the authenticated user’s `company_id` is used.

### `GET /api/v1/internal/intelligence/overview`

High-level summary: event totals in window, distinct event / aggregate-type counts, count of `event_record_failures` in the same window, feature-flag snapshot, and relative paths to other Phase 2 endpoints.

### `GET /api/v1/internal/intelligence/insights`

Aggregations over `domain_events`:

- Total events in window.
- Counts grouped by `event_name` and `aggregate_type`.
- Daily histogram (built via a read cursor over `occurred_at` — portable across SQLite / PostgreSQL).
- First / last `occurred_at` in window.

### `GET /api/v1/internal/intelligence/recommendations`

**Heuristic** recommendations derived from insight aggregates only. Not financial or operational advice; no execution.

### `GET /api/v1/internal/intelligence/alerts`

**Read-only** threshold notices, e.g.:

- Volume spike (last 24h vs prior 24h).
- Presence of `event_record_failures` in the last 7 days (scoped).
- Optional informational alert if persistence is enabled in config but the window has zero events.

No alerting channels are wired; the API only **returns** alert objects.

## Code map

| Area | Location |
|------|-----------|
| Middleware (master flag) | `app/Http/Middleware/EnsurePhase2ReadonlyEnabled.php` |
| Controller | `app/Http/Controllers/Api/V1/Internal/Phase2IntelligenceController.php` |
| Query scoping | `app/Services/Intelligence/Phase2/Phase2DomainEventQuery.php` |
| Insights | `app/Services/Intelligence/Phase2/Phase2InsightsService.php` |
| Recommendations | `app/Services/Intelligence/Phase2/Phase2RecommendationsService.php` |
| Alerts | `app/Services/Intelligence/Phase2/Phase2AlertsService.php` |
| Overview | `app/Services/Intelligence/Phase2/Phase2OverviewService.php` |
| Routes | `routes/api.php` (internal + `intelligent.phase2` group) |
| Middleware alias | `bootstrap/app.php` → `intelligent.phase2` |

## Tests

`tests/Feature/Intelligent/Phase2ReadonlyApiTest.php` covers:

- 404 when Phase 2 master or internal dashboard is off.
- 404 when a specific feature flag is off.
- 403 for non-admin roles.
- 200 and JSON shape for owner when all flags are on (test-only `config([...])`).
- **Read-only guarantee:** row counts for `domain_events`, `invoices`, and `wallet_transactions` are unchanged after calling all four GET endpoints.

Run:

```bash
docker exec saas_app php artisan test tests/Feature/Intelligent/Phase2ReadonlyApiTest.php
```

## Enabling in a non-production environment

Example `.env` fragment (adjust per environment):

```env
INTELLIGENT_INTERNAL_DASHBOARD_ENABLED=true
INTELLIGENT_PHASE2_ENABLED=true
INTELLIGENT_PHASE2_OVERVIEW_ENABLED=true
INTELLIGENT_PHASE2_INSIGHTS_ENABLED=true
INTELLIGENT_PHASE2_RECOMMENDATIONS_ENABLED=true
INTELLIGENT_PHASE2_ALERTS_ENABLED=true
```

Phase 1 event capture remains independent (`INTELLIGENT_EVENTS_*`). Phase 2 is useful when `domain_events` already contains data (or to confirm empty windows).

## Relationship to `domain_events` schema

Events are persisted by Phase 1 (`DomainEventRecorder`) when flags allow. Phase 2 **only reads** columns such as:

`company_id`, `branch_id`, `aggregate_type`, `aggregate_id`, `event_name`, `occurred_at`, `trace_id`, `correlation_id`, etc.

No schema changes are required for Phase 2.

---

*Document version: Phase 2 initial delivery — read-only intelligence surface.*
