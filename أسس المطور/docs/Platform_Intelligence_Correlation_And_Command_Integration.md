# Platform Intelligence — Correlation & Command Integration

## 1) Goal

Unify **read-only** visibility across existing intelligence layers (signals, candidates, incidents, decisions, guided workflow runs, overview-derived metrics) through a **documented correlation model** and a **single command surface**, improving operator situational awareness **without** new mutations, controlled actions, remediation, or finance-domain coupling.

## 2) Phase boundaries

**In scope**

- Correlation rules with explicit **relation types** (`causal`, `contextual`, `derived`, `timeline_related`).
- `GET /api/v1/platform/intelligence/command-surface` aggregated dashboard payload.
- `GET /api/v1/platform/intelligence/incidents/{incident_key}/correlation` incident workspace bundle.
- Analytical trace hooks (`correlation_built`, `command_surface_rendered`, `incident_context_linked`, …) via `CorrelationTraceEmitter` (optional recorder binding).
- Frontend navigation + incident correlation panel (read-only).

**Out of scope**

- Controlled Actions, broad automation, remediation, external command execution.
- Ledger / wallets / posting / reconciliation or any new financial writes.
- Merging lifecycle, decision log, workflow history, or intelligence traces into one ambiguous entity.

## 3) Correlation model

| Link | Rule (summary) |
| --- | --- |
| Signal → Incident (causal) | `signal_key` ∈ `incident.source_signals` |
| Signal → Incident (contextual) | Overlapping `affected_companies` **and** intersection between `signal.correlation_keys` and `incident.source_signals` |
| Signal → Incident (contextual, weaker) | Company overlap only |
| Candidate → Incident (derived) | `candidate.incident_key === incident.incident_key` |
| Decision → Incident (timeline) | DB row `incident_key` match |
| Workflow run → Incident (timeline) | `platform_guided_workflow_idempotency.incident_key` match |

Implementation: `CorrelationRuleEvaluator`, `IncidentCorrelationAssembler`.

## 4) Relation types

See `App\Support\PlatformIntelligence\Enums\CorrelationRelationType`.

## 5) Command surface scope

Sections (all read-only):

- Prioritized open **high/critical** incidents
- Recently **escalated** incidents
- Sample **monitoring** incidents (staleness visibility)
- Decisions with `follow_up_required` (requires `decisions.read`)
- Recent **workflow idempotency** executions
- Signals **not referenced** by any **open** incident `source_signals` (requires `signals.read`)
- Candidates whose `incident_key` is **not materialized** (requires `signals.read` + `candidates.read`)
- **Company stacked signal** counts from current signal snapshot

Builder: `CommandSurfaceAssembler` + `CommandSurfacePrioritizer`.

## 6) Prioritization logic

`CommandSurfacePrioritizer::incidentScore()` uses documented weights (severity, escalation flag, non-terminal status, freshness hint) with **stable tie-break**: ascending **lexicographic** order on `incident_key` when scores tie.

## 7) Explainability rules

Every correlated item in API payloads includes:

- `relation_type`
- `relation_reason` (machine key)
- `compact_why` (short Arabic operator copy where applicable)

Silent links are **not** emitted.

## 8) Permission model

- Both endpoints require `platform.intelligence.incidents.read`.
- Subsections omit or gate data when the operator lacks `signals.read`, `candidates.read`, or `decisions.read` (see `meta.permissions_used`).

## 9) Freshness / performance policy

- Reuses **one** signal engine build per command-surface request when signals are enabled.
- Candidate engine invoked only when both signals + candidates reads are granted.
- Incident correlation caps list scans (signals/candidates) to avoid runaway CPU.
- Stable ordering documented in `meta.ordering` fields.

## 10) UI scope

- `/platform/intelligence/command` — command surface view.
- Incident detail embeds correlation summary panel with deep links to existing intelligence routes.

## 11) Relationship to Controlled Actions (future)

This phase exposes **context and prioritization only**. Controlled Actions may later attach to the same correlation identifiers, but **must not** reuse this endpoint for execution.

## 12) Closure criteria

- Correlation rules + prioritizer covered by PHPUnit.
- Feature tests verify permissions, happy paths, 404 behaviour.
- Guardrails forbid `POST` on command/correlation intelligence paths.
- Documentation (this file) complete.

## 13) Transition blockers

Do not start Controlled Actions if correlation explainability is missing, permissions leak in UI, or command surface responses are unstable between refreshes without an explicit TTL policy.

---

**Primary code paths**

- `backend/app/Support/PlatformIntelligence/Correlation/`
- `backend/app/Support/PlatformIntelligence/CommandCenter/`
- `backend/app/Support/PlatformIntelligence/CommandPrioritization/`
- `backend/app/Http/Controllers/Api/V1/PlatformIntelligenceCommandSurfaceController.php`
