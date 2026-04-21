# Platform Intelligence — Guided Workflows (MVP)

## 1) Goal

Provide **structured, human-operated flows** that compose **existing** incident lifecycle actions and decision recording—reducing variance between operators while staying **strictly outside** automation engines, remediation, finance domains, and broad BPM.

## 2) Phase boundaries

**In scope**

- Canonical workflow keys with explicit **preconditions**, **required permissions** (intersection of underlying IAM keys), and **step previews**.
- **Backend orchestration** (`GuidedWorkflowExecutor`) calling only `IncidentLifecycleService` and `DecisionRecordingService`.
- **Trace events**: `workflow_started`, `workflow_completed`, `workflow_failed` (intelligence trace layer — not merged into decision rows or lifecycle rows).
- **Idempotency** persistence (`platform_guided_workflow_idempotency`) + cache lock to mitigate double submission.
- **API**: `GET .../incidents/{key}/workflows`, `POST .../incidents/{key}/workflows/execute`.
- **UI panel** on incident detail with confirmation gate.

**Out of scope**

- Auto-remediation, auto-fix, ledger/wallets/posting/reconciliation.
- Approval/BPM engines, arbitrary workflow authoring, external command integrations.
- New domain write surfaces beyond the already-approved incident + decision APIs.

## 3) Workflow definitions (MVP set)

| `workflow_key` | Preconditions (structural) | Permissions | Effect (preview) |
| --- | --- | --- | --- |
| `acknowledge_assign` | `open` or `acknowledged` | acknowledge + assign_owner | acknowledge if `open` → assign owner |
| `under_review_decision` | `acknowledged` | acknowledge + decisions.write | move_under_review → decision(observation) |
| `escalate_decision` | `under_review` | escalate + decisions.write | escalate → decision(escalation) |
| `monitor_transition` | `under_review` or `escalated` | acknowledge | move_monitoring |
| `monitor_with_decision` | `under_review` or `escalated` | acknowledge + decisions.write | move_monitoring → decision(monitor) |
| `resolve_closure` | `monitoring` or `escalated` | resolve + decisions.write | resolve → decision(closure) |
| `close_final` | `resolved` | close + decisions.write | close → decision(closure) |
| `false_positive` | `monitoring` or `escalated` | resolve + decisions.write | decision(false_positive) → resolve |

Payload rules (selected):

- `confirm` must be accepted (`true`).
- `idempotency_key` UUID required on execute.
- `owner_ref` required for `acknowledge_assign`.
- `decision_summary` + `rationale` (min length enforced server-side) when a decision is part of the flow.
- `expected_outcome` required for `monitor_with_decision`.
- `close_reason` required for `close_final`.

## 4) Permission model

- Route gate for execute: `platform.intelligence.guided_workflows.execute`.
- Capability: `execute_guided_workflows` → same permission key.
- **Each workflow additionally requires** the intersection of underlying permissions (enforced in `GuidedWorkflowExecutor`); missing any yields `403` with `missing_permission:...`.
- Catalog listing uses `platform.intelligence.incidents.read`.

## 5) Separation of concerns

| Layer | Responsibility |
| --- | --- |
| Workflow | Orchestration + validation + trace envelope |
| Lifecycle | Status transitions only (existing service) |
| Decision log | `PlatformDecisionLogEntryContract` rows only |
| Intelligence trace | Supplementary `Workflow*` events |

## 6) Traceability

`GuidedWorkflowTraceEmitter` emits:

- `WorkflowStarted` / `WorkflowCompleted` / `WorkflowFailed` with `context.workflow_key` and optional payload fragments (never replaces persisted decisions or lifecycle rows).

## 7) UI scope

Incident detail embeds `PlatformIncidentGuidedWorkflowsPanel`:

- Lists all workflows with availability + reason when disabled.
- Requires explicit confirmation checkbox before POST.
- Collects only the inputs required by the selected workflow.

## 8) Relationship to Correlation & Command Integration

Future correlation/command phases may **reference** `workflow_key` + trace correlation IDs, but this MVP **does not** publish commands to external systems or enrich incidents with automated correlations.

## 9) Closure checklist

- Definitions live under `App\Support\PlatformIntelligence\GuidedWorkflows` + `WorkflowExecution`.
- Controller stays thin; orchestration in `GuidedWorkflowExecutor`.
- PHPUnit feature coverage for permissions, preconditions, happy paths, idempotency, confirmation validation.
- No finance namespace coupling (existing guardrails still apply).

## 10) Transition blockers (to Correlation & Command Integration)

Do not proceed if workflow availability drifts from backend truth, idempotency is bypassed, or trace events are dropped from the intelligence recorder path in production.

---

**Primary paths**

- Backend: `app/Support/PlatformIntelligence/GuidedWorkflows/`, `app/Support/PlatformIntelligence/WorkflowExecution/`
- HTTP: `App\Http\Controllers\Api\V1\PlatformIncidentWorkflowController`
- Frontend: `usePlatformGuidedWorkflows.ts`, `PlatformIncidentGuidedWorkflowsPanel.vue`
