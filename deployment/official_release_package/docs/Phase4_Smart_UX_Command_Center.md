# Phase 4 — Smart UX / Cognitive UI (Command Center)

## Purpose

Internal **read-only** operational cockpit for owners and managers. It surfaces Phase 2 intelligence (overview, insights, recommendations, alerts) and a **Phase 4 aggregate** endpoint that buckets signals into **NOW / NEXT / WATCH**. No writes, no automation, no financial mutations.

## Route / page

| Item | Value |
|------|--------|
| **Path** | `/internal/intelligence` |
| **Route name** | `internal.intelligence` |
| **Vue view** | `frontend/src/views/internal/IntelligenceCommandCenterView.vue` |
| **Access** | Staff layout, **manager or owner** (`requiresManager`), plus frontend flag (see below). |

## Feature flags

### Backend

- **`INTELLIGENT_INTERNAL_DASHBOARD_ENABLED`** — required for any `/api/v1/internal/*` intelligence route (existing).
- **`INTELLIGENT_READ_MODELS_ENABLED`** or **`INTELLIGENT_PHASE2_ENABLED`** — Phase 2 middleware (existing).
- Per-endpoint Phase 2 flags for overview, insights, recommendations, alerts (existing).
- **`INTELLIGENT_COMMAND_CENTER_ENABLED`** — enables **`GET /api/v1/internal/intelligence/command-center`** (Phase 4 aggregate).  
  Legacy alias: `intelligent.phase2.features.command_center` in config.

### Frontend

- **`VITE_INTELLIGENCE_COMMAND_CENTER=true`** — shows sidebar link and allows navigation. If unset/false, direct access to `/internal/intelligence` redirects to the dashboard.

## APIs consumed (all GET, read-only)

| Endpoint | Role |
|----------|------|
| `/api/v1/internal/intelligence/overview` | Meta, flags, summary |
| `/api/v1/internal/intelligence/insights` | Counts, window, activity |
| `/api/v1/internal/intelligence/recommendations` | Rule-based suggestions |
| `/api/v1/internal/intelligence/alerts` | Threshold alerts (+ dedicated panel) |
| `/api/v1/internal/intelligence/command-center` | **NOW / NEXT / WATCH** zones (max 5 items per zone) |

No `POST` / `PUT` / `PATCH` / `DELETE` from this page.

## Backend additions (additive)

- **`App\Services\Intelligence\Phase4\Phase4CommandCenterService`** — composes existing Phase 2 services only; SELECT/read logic unchanged.
- **`Phase2IntelligenceController::commandCenter`** — returns `meta.phase: 4`, `meta.read_only: true`.
- **Config** `intelligent.command_center_api.enabled` (`INTELLIGENT_COMMAND_CENTER_ENABLED`).
- **Tests** in `tests/Feature/Intelligent/Phase2ReadonlyApiTest.php` (command-center OK + 404 when disabled + no DB mutation).

## Frontend components

Under `frontend/src/components/intelligence/`:

| Component | Responsibility |
|-----------|----------------|
| `IntelligenceSummaryStrip` | Totals NOW / NEXT / WATCH, read-only badge, refresh, trace |
| `CommandZoneCard` | Section header + list of item cards or empty state |
| `CommandItemCard` | Severity, title, why_now, suggested_action, impact |
| `SeverityBadge` | Visual severity (RTL-friendly layout via parent `dir`) |
| `SuggestedActionBlock` | Highlighted suggested action text |
| `EmptySignalState` | Low-signal / empty zone messaging |

**Composable:** `frontend/src/composables/useIntelligenceCommandCenter.ts` — parallel GETs, loading/error/empty handling.

## Empty-state behavior

- **Per zone:** If a zone has no items, `EmptySignalState` explains that signals appear when telemetry and heuristics produce data.
- **Global low signal:** When the command-center payload reports `summary.low_signal`, an amber banner explains that more domain events (Phase 1 persistence) improve coverage.
- **API off / 403:** Full-page error with retry; partial success (e.g. insights OK, command-center 404) still renders available panels without blocking the whole page.

## Safety guarantees

- UI **never** calls mutation endpoints for this feature.
- Backend command-center path is **GET-only** and uses existing read services.
- Financial flows and routes are **unchanged**.
- Defaults: command-center API and UI flag are **off** until explicitly enabled.

## UX notes

- Arabic-first copy; layout inherits **`dir`** from `AppLayout` / locale (RTL/LTR).
- Card-first layout, no large tables by default.
- Alerts panel prioritizes **warning** severity, caps visible list at **5**.

## Next phase recommendation

- **Phase 5 (optional):** Deep links from `related_entity_references` when the read model exposes stable entity IDs; optional export (CSV/PDF) of the snapshot; role-scoped views (owner vs manager) if policies diverge.
