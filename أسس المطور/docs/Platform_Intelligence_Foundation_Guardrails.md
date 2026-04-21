# Platform Intelligence Operations — Foundation Guardrails (Phase 1)

Official reference for **FOUNDATION GUARDRAILS** before Priority 7, Incident Center UI, or Decision Log UI.

**Next phase (implemented on top of this foundation):** see `docs/Platform_Intelligence_Priority7_Signal_Engine.md` (read-only Signal Engine + `GET /platform/intelligence/signals`).

## 1) Goal

Establish operational, governance, and technical **contracts** so later phases stay safe, traceable, and scalable — **without** touching accounting, ledger, wallet, posting, settlement, or any sensitive financial mutation paths.

## 2) Core concepts (strict separation)

| Concept | Definition |
|--------|------------|
| **Signal** | A single observable fact or derived detection (`PlatformSignal*`). Not actionable as an incident by itself. |
| **Incident Candidate** | A correlated grouping of signal keys suggesting work (`PlatformIncidentCandidate*`). Not yet under incident lifecycle. |
| **Incident** | A managed lifecycle record with status, ownership, escalation (`PlatformIncident*`). |
| **Decision** | An immutable log entry documenting rationale (`PlatformDecisionLogEntry*`). Not an executable action. |
| **Action** | Future bounded execution intent (out of scope for Phase 1). Must never be conflated with Signal, Incident, or Decision types or names. |

Mixing these names in APIs, DTOs, or UI is **not allowed**.

## 3) Allowed vs forbidden (Phase 1)

**Allowed**

- Domain contracts (PHP + TypeScript), enums, transition policy, permission matrix, trace **abstractions**, read-only wiring, tests, and this document.

**Forbidden**

- Any change to ledger, journal, wallet, reconciliation, invoice financial posting, or tenant financial mutations.
- Any operational **mutation** API or job introduced under `platform.intelligence` in this phase.
- Free-text enums where a canonical enum exists.
- UI-heavy Incident Center / Decision Log screens (deferred).

## 4) Non-interference with finance / ledger

Code for this phase lives under:

- `backend/app/Support/PlatformIntelligence/`
- `frontend/src/types/platform-admin/platformIntelligence*.ts`
- `frontend/src/composables/platform-admin/*intelligence*`

It **must not** import or call namespaces under accounting, ledger, wallet, or payment settlement. CI-style guard tests assert this.

## 5) Read-only foundation

Phase 1 is **read-model / policy / IAM** only. Trace recorders default to **null** (no noisy logs). In-memory recorders exist for tests only.

### 5.1) Tracing scope (important)

The trace layer delivered here is **structural / foundational only**: interfaces, event shape, null recorder, and in-memory recorder for tests.

It is **not** yet a durable production operational log for incidents or decisions (no mandated persistence pipeline, no retention/SLO contract, no operator-facing trace UI in this phase). Future phases may bridge to `platform_audit_logs` or a dedicated store **only** with an explicit design — do not assume “full production traceability” is already shipped because these types exist.

## 6) Incident status transition policy

Authoritative edges (same in PHP and TS):

1. `open` → `acknowledged`
2. `acknowledged` → `under_review`
3. `under_review` → `escalated`
4. `under_review` → `monitoring`
5. `escalated` → `monitoring`
6. `escalated` → `resolved`
7. `monitoring` → `resolved`
8. `resolved` → `closed`

Same-state (`X` → `X`) is allowed for idempotent UI. Any other transition must be rejected by `PlatformIncidentStatusTransitionPolicy` (backend) and `incidentStatusTransitionPolicy` (frontend).

## 7) Permission policy

Capabilities map 1:1 to IAM keys in `PlatformOperatorPermissionMatrix` / `platformIntelligencePermissionMatrix.ts`.

Roles are configured in `config/platform_roles.php`. **Auditor** and **finance_admin** are read-only for intelligence in this release. **Support** may acknowledge, assign, and add decision entries but not escalate/resolve/close. **Operations** and **platform_admin** carry full incident lifecycle permissions for future APIs.

The SPA merges platform grants into `/auth/me` permissions; `hasPermission('platform.*')` **never** uses the tenant-owner shortcut.

## 8) Traceability requirements

Each trace event carries at minimum: `event_type`, `actor`, `timestamp`, `source`, `reason`, optional `correlation_id` / `trace_id`, `linked_entity_key`, and optional structured `context`. Implementations must not spam production logs by default.

## 8.1) PHP / TS enum drift control (before Priority 7)

Canonical string unions for shared enums live in:

- `backend/tests/fixtures/platform_intelligence_canonical_enum_values.json`

**Both** sides are guarded:

- PHP: `Tests\Unit\PlatformIntelligence\PlatformIntelligenceEnumJsonParityTest`
- TS: `frontend/src/types/platform-admin/platformIntelligenceEnumJsonParity.test.ts`

When adding or renaming a value: update the JSON fixture first, then PHP enums, then TS const arrays, then run both test suites. Do not introduce ad hoc string literals for these domains in components or composables.

## 9) Closure criteria (Phase 1)

Phase 1 is complete only when:

1. Canonical contracts exist for all five entity families (including transition policy + permission matrix types).
2. Central enums exist in PHP and TS with identical string values (enforced against `platform_intelligence_canonical_enum_values.json`).
3. Transition policy is implemented centrally and covered by tests (frontend + backend).
4. Permission matrix is explicit and enforced via `PlatformPermissionService::intelligenceCapabilityGranted` and SPA gates.
5. Trace abstraction exists (interface + null + in-memory).
6. This document exists and matches the code.
7. Tests cover enums, transitions, permissions, contract field presence, and guardrails (no finance imports; no intelligence mutation routes).
8. **Backend tests have been executed on a real PHP environment** (see §9.1).

### 9.1) Mandatory backend verification (official closure)

Run on a machine/container with PHP and project dependencies:

```bash
cd backend
php artisan test --filter=PlatformIntelligence
```

Because Phase 1 touched **auth permission snapshots** (`AuthController` merge of platform grants) and IAM, also run a **small sequential smoke bundle** (avoid running very broad `--filter=Auth` in parallel with other suites against the same Postgres — RefreshDatabase races can produce false failures):

```bash
php artisan test tests/Unit/Services/PlatformPermissionServiceTest.php \
  tests/Feature/Auth/LoginTest.php \
  tests/Feature/Auth/LoginAccountContextTest.php \
  tests/Feature/Auth/AuthApiContractTest.php

php artisan test tests/Feature/Platform/PlatformAuthPermissionsMergeTest.php
```

`PlatformAuthPermissionsMergeTest` asserts that **platform** operators receive merged `platform.intelligence.*` keys in **login + `/auth/me`**, while **pure tenant** owners do not.

**Smoke intent (not a manual checklist product):** after the above, confirm that **platform** and **non-platform** users still receive coherent `permissions` arrays and that platform-only keys (`platform.intelligence.*`) appear only when `PlatformRolePermissionResolver` applies — without breaking tenant login.

### 9.2) Priority 7 — no parallel shapes

Any new Signal (or derived read model) in Priority 7 **must** conform to `PlatformSignalContract` (and official enums). **Do not** invent parallel ad hoc interfaces inside Vue composables or leaf components; extend the contracts or add versioned DTOs under `backend/app/Support/PlatformIntelligence/` and `frontend/src/types/platform-admin/` only.

## 10) Do not start Priority 7 if…

- Contracts or enums are incomplete or duplicated ad hoc.
- Status transitions are UI-only without shared policy.
- Permissions are UI-only (hidden buttons without IAM keys / service checks).
- This document or tests are missing.
- Any ledger/finance coupling appears in the intelligence package.
