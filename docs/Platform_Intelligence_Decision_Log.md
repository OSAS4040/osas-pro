# Platform Intelligence — Decision Log (Recording Layer)

## 1) Goal of this phase

Introduce an **institutional Decision Log** as a **first-class persisted artifact** linked to **official incidents** (`PlatformIncidentContract` / `platform_incidents`). Operators can record **reviewable decisions** with strict schema, permissions, and traceability—**without** guided workflows, remediation, financial side effects, or coupling to incident lifecycle transitions.

## 2) Phase boundaries

**In scope**

- `PlatformDecisionLogEntryContract` as the **only** wire/storage DTO for a decision row.
- Enum `PlatformDecisionType` with **six** canonical values.
- Dedicated table `platform_decision_log_entries`.
- Read APIs scoped by `incident_key`, plus a query-parameter list endpoint.
- Single **POST** create endpoint nested under the incident resource path.
- Optional intelligence trace `platform_intelligence.decision_recorded` (supplementary—not the decision entity itself).
- UI panel on incident detail: list + restrained create form.

**Out of scope (explicit)**

- Guided workflows, approval engines, action execution.
- Auto-remediation / auto-fix / domain mutations (ledger, wallets, posting, reconciliation, etc.).
- PATCH/DELETE on decision entries in this MVP.
- Treating lifecycle rows as decision records, or auto-creating decisions on status change.

## 3) What this phase produces

1. Persisted **decision entries** with stable `decision_id` (UUID).
2. **Strict validation** on create (type, summary, rationale, bounded optional arrays).
3. **Permission gates**: `platform.intelligence.decisions.read`, `platform.intelligence.decisions.write`.
4. **Separation guarantee**: recording a decision does **not** change `platform_incidents.status` or append `platform_incident_lifecycle_events`.
5. **Trace fan-out** via `DecisionTraceEmitter` → `PlatformIntelligenceTraceEventType::DecisionRecorded`.

## 4) What this phase does *not* produce

- Workflow builder UI, state machines for approvals, or execution bridges.
- Decision-driven incident transitions (those remain in `PlatformIncidentLifecycleController` only).

## 5) Decision contract

Canonical PHP: `App\Support\PlatformIntelligence\Contracts\PlatformDecisionLogEntryContract`.

Fields: `decision_id`, `incident_key`, `decision_type`, `decision_summary`, `rationale`, `actor` (`user:{id}`), `created_at`, `linked_signals`, `linked_notes`, `expected_outcome`, `evidence_refs`, `follow_up_required`.

Serialization: `PlatformDecisionLogEntryContractSerializer::toArray()`.

## 6) Decision types

| Value | Meaning (summary) |
| --- | --- |
| `observation` | Documented observation |
| `escalation` | Institutional escalation stance |
| `false_positive` | Classification after review |
| `monitor` | Continue watch stance |
| `closure` | Institutional closure stance (not the same as incident `closed` status) |
| `action_approved` | Approval **documentation only** — no execution in this phase |

## 7) Recording rules

- Target incident **must** exist in `platform_incidents`.
- `decision_type`, `decision_summary`, `rationale` are mandatory and length-validated.
- `actor` is always the authenticated platform user on write.
- Optional arrays are normalized to non-empty string lists with caps (see `StorePlatformDecisionLogEntryRequest`).

## 8) Permission model

| Permission | Use |
| --- | --- |
| `platform.intelligence.decisions.read` | GET list endpoints |
| `platform.intelligence.decisions.write` | POST create |

Backend: `platform.permission` middleware on routes.  
Frontend: `PLATFORM_INTELLIGENCE_PERMISSIONS.decisionsRead` / `decisionsWrite` mirror for UI only.

## 9) UI scope

- **Incident detail** embeds `PlatformIncidentDecisionLogPanel` (list + form when write is granted).
- Arabic labels: `PLATFORM_DECISION_TYPE_LABEL_AR` in `platformIntelligenceEnums.ts`.
- No standalone workflow screens in this MVP.

## 10) Traceability

- Persisted rows in `platform_decision_log_entries` are the **source of truth**.
- Additional optional trace: `DecisionRecorded` with `context.decision_id` and `context.decision_type`.

## 11) Relationship to future Guided Workflows

Guided workflows may **consume** decision entries as inputs (e.g., required decision before a step), but this phase **does not** orchestrate steps. The stable `decision_id` + `incident_key` linkage is the integration surface.

## 12) Closure criteria

- Contract + enum parity tests pass.
- Feature tests prove: permissions, validation, ordering/filter, **no lifecycle mutation** on POST, trace emission when recorder is bound.
- Guardrail: no loose `POST /platform/intelligence/decisions` route; writes only under `/incidents/{key}/decisions`.
- No finance/ledger references under `PlatformIntelligence` support tree (existing guardrail test).

## 13) Transition blocking (to Guided Workflows)

Do not start workflow orchestration until:

- Decision schema and permissions are stable in production-like environments.
- Incident linkage and audit expectations are signed off.
- Any need for PATCH/soft-delete/versioning is designed explicitly (not implied by this MVP).

---

**Primary paths**

- Backend: `app/Support/PlatformIntelligence/DecisionLog/`, `DecisionSerialization/`
- API: `App\Http\Controllers\Api\V1\PlatformDecisionLogController`
- Frontend: `composables/.../usePlatformIncidentDecisions.ts`, `components/.../PlatformIncidentDecisionLogPanel.vue`
