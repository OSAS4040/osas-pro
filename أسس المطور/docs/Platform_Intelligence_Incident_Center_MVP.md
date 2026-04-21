# Platform Intelligence — Incident Center MVP

## 1) Goal of this phase

Deliver a **controlled operational surface** for platform staff to work with **materialized incidents** derived from the official **Platform Incident Candidate** pipeline. The Incident Center is for **visibility, lifecycle, ownership, escalation, and traceability** only—not for financial operations, ledger changes, remediation execution, or a full Decision Log.

## 2) Phase boundaries (non‑negotiable)

**In scope**

- Persisted incidents with a stable **`incident_key`** aligned to the candidate contract key.
- Official **`PlatformIncidentContract`** serialization for API responses.
- Policy‑enforced lifecycle, ownership, escalation, resolve/close with reasons where required.
- Operational trace events (not a Decision Log).
- Platform admin UI: list, filters, sort, detail, timeline, safe actions.

**Out of scope (explicitly forbidden in this phase)**

- Any change to **ledger**, **posting**, **reconciliation**, **wallets**, or other finance domains.
- **Auto‑remediation**, **auto‑fix**, or imperative “fix data” commands.
- **Decision Log** product surface, schema, or workflows.
- Broad institutional workflow engines beyond the MVP lifecycle.
- Treating **`PlatformIncidentCandidateContract`** as the persisted incident shape in the UI (candidates remain a separate concern; incidents are materialized).

## 3) What Incident Center MVP produces

1. **Materialization layer** — single path from candidate snapshot → persisted incident row → `PlatformIncidentContract`.
2. **Stable incident identity** — deterministic ordering keys and timestamps from persistence.
3. **Lifecycle policy** — finite state machine with allow‑listed transitions only.
4. **Ownership layer** — assign / reassign / unassigned for **incident coordination** only.
5. **Escalation state** — orthogonal flag for coordination, not external execution.
6. **Traceability** — append‑only lifecycle events with actor, timestamps, prior/next payload, reasons when applicable.
7. **API** — read list/detail; narrow POST endpoints for each safe action.
8. **UI** — Incident Center list + Incident Detail workspace (lite).

## 4) What it does *not* produce

- Decision records, approvals, or decision history product.
- Remediation playbooks tied to system mutations.
- HR / workload scheduling tied to ownership.
- Cross‑domain side effects (billing, tenants, ledger, etc.).

## 5) Incident identity model

Canonical contract: **`PlatformIncidentContract`** (`backend/app/Contracts/PlatformIntelligence/PlatformIncidentContract.php`).

Minimum fields (MVP):

| Field | Role |
| --- | --- |
| `incident_key` | Stable primary identity (matches candidate key). |
| `incident_type` | Classification string from candidate. |
| `title`, `summary` | Human‑readable headline and narrative. |
| `why_summary` | Short “why surfaced” explanation from candidate. |
| `severity`, `confidence` | From candidate; stored on incident. |
| `status` | Lifecycle state (see §6). |
| `owner` | Nullable owner user id. |
| `ownership_state` | `unassigned` \| `assigned`. |
| `escalation_state` | `none` \| `pending` \| `escalated` \| `contained`. |
| `affected_scope`, `affected_entities`, `affected_companies` | Scope mirrors candidate contract shapes. |
| `source_signals` | Grouped signal keys from candidate. |
| `recommended_actions` | Advisory strings only (no execution). |
| `first_seen_at`, `last_seen_at` | From candidate timestamps. |
| `acknowledged_at`, `resolved_at`, `closed_at` | Set by lifecycle transitions. |
| `last_status_change_at` | Updated on every successful status transition. |
| `resolve_reason`, `close_reason` | Required text on resolve/close. |

**Determinism:** list API orders by `last_seen_at` DESC, then `incident_key` ASC to avoid refresh jitter.

## 6) Lifecycle policy

**Statuses**

`open` → `acknowledged` → `under_review` → (`escalated` \| `monitoring`) → `resolved` → `closed`

**Allowed transitions**

| From | To |
| --- | --- |
| `open` | `acknowledged` |
| `acknowledged` | `under_review` |
| `under_review` | `escalated` |
| `under_review` | `monitoring` |
| `escalated` | `monitoring` |
| `escalated` | `resolved` |
| `monitoring` | `resolved` |
| `resolved` | `closed` |

**Disallowed examples**

- `closed` directly from `open` or any non‑`resolved` state.
- Any transition not in the table above → **422** with `IncidentLifecycleException`.

Implementation: `IncidentLifecyclePolicy` + `IncidentLifecycleService`.

## 7) Ownership model

- **`owner`**: nullable FK to `users.id`.
- **`ownership_state`**: `unassigned` when `owner` is null; `assigned` otherwise.
- **Actions**: assign / reassign (same permission gate). No scheduling, no delegation of privileged system operations.

## 8) Escalation model

- **`escalation_state`** values: `none`, `pending`, `escalated`, `contained`.
- Entering **`escalated`** status sets `escalation_state` to `escalated` and clears `pending`.
- Moving to **`monitoring`** from `escalated` sets `contained`.
- Other transitions preserve escalation unless explicitly updated by policy (see service).

Escalation is **coordination metadata** only—no external ticketing integration in MVP.

## 9) Allowed safe actions (operator)

| Action | Permission constant | HTTP |
| --- | --- | --- |
| Materialize from candidate | `platform.intelligence.incidents.materialize` | `POST /platform/intelligence/incidents/materialize` |
| Acknowledge | `platform.intelligence.incidents.acknowledge` | `POST .../acknowledge` |
| Move to under review | `platform.intelligence.incidents.acknowledge` (same gate as coordination) | `POST .../move-under-review` |
| Escalate | `platform.intelligence.incidents.escalate` | `POST .../escalate` |
| Move to monitoring | `platform.intelligence.incidents.escalate` | `POST .../move-monitoring` |
| Assign / reassign owner | `platform.intelligence.incidents.assign_owner` | `POST .../assign-owner` |
| Resolve (reason required) | `platform.intelligence.incidents.resolve` | `POST .../resolve` |
| Close (reason required) | `platform.intelligence.incidents.close` | `POST .../close` |
| Append operational note | `platform.intelligence.incidents.notes` | `POST .../notes` |

No other POST verbs are registered for incidents.

## 10) Permission model

Backend: `permission:` middleware on every route; 403 if missing.

Frontend: `PLATFORM_INTELLIGENCE_PERMISSIONS` + `useAuthStore().hasPermission()` gates buttons (mirrors backend; not a security boundary alone).

## 11) Traceability events

Persisted in `platform_incident_lifecycle_events` with enum `IncidentOperationalTraceEvent`:

- `incident_materialized`
- `incident_acknowledged`
- `incident_owner_assigned`
- `incident_reassigned`
- `incident_escalated`
- `incident_moved_to_monitoring`
- `incident_resolved`
- `incident_closed`
- `incident_note_appended`

Each row stores: `actor_user_id`, `incident_key`, `event_type`, `payload_json` (prior snapshot, next snapshot, reasons, note excerpt).

## 12) Relationship to the future Decision Log

This MVP produces **structured incident history** and **operational traces** suitable to **link** to a future Decision Log (foreign keys / correlation ids), but **does not** implement decision entities, decision states, or approvals. No decision endpoints exist under `/platform/intelligence/decisions/*`.

## 13) Closure criteria (phase done when)

All of the following are true:

1. Incident Center UI routes exist and render stable list/detail states.
2. `PlatformIncidentContract` is the only incident DTO on the wire.
3. Lifecycle + resolve/close rules enforced in backend with tests.
4. Permissions enforced on every mutation.
5. Trace rows emitted for each transition in the happy path.
6. Automated tests cover materialization, policy, permissions, API, and contract shape.
7. No finance/ledger/wallet code paths were touched for this feature.

## 14) Transition blocking rules (engineering)

Do **not** start Decision Log until:

- Lifecycle, ownership, escalation, resolve/close, and traceability are stable under load tests appropriate for your environment.
- Contract drift tests fail CI if unofficial incident shapes appear in frontend types.
- All incident mutations remain narrowly scoped POST actions (no generic PATCH incident).

---

**Primary code locations**

- Backend: `backend/app/Support/PlatformIntelligence/IncidentCenter/`, `IncidentLifecycle/`, `IncidentSerialization/`
- API: `PlatformIntelligenceIncidentsController`, `PlatformIncidentLifecycleController`
- Frontend: `frontend/src/views/platform/PlatformIncidentCenterView.vue`, `PlatformIncidentDetailView.vue`, composables under `frontend/src/composables/platform-admin/intelligence/`
