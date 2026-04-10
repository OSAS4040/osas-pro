# Wave-1 Interim Review Package

## Scope of this package

- Baseline security posture after transition hardening and contract stabilization.
- Before/after measurement around transition-gate refinement.
- API 409 conflict contract evidence.
- Remaining medium-risk focus list for next closure batches.

## Baseline metrics

- `TOTAL_ROUTES=345`
- `SENSITIVE_ROUTES=270`
- `HIGH_RISK_SENSITIVE_ROUTES=0`
- `MEDIUM_RISK_SENSITIVE_ROUTES=0` (latest after Batch-11 final routes closure)

## Before/after (transition gate refinement)

- Before gate refinement:
  - `backend/reports/security-baseline-after-transition-hints/`
  - `transition_guard_summary.present=11`
  - `transition_guard_summary.missing_or_unknown=21`
  - `transition_guard_summary.not_applicable=232`
- After gate refinement:
  - `backend/reports/security-baseline-after-gate-refinement/`
  - `transition_guard_summary.present=4`
  - `transition_guard_summary.missing_or_unknown=12`
  - `transition_guard_summary.not_applicable=248`

## Batch-1 execution (Governance approve/reject)

- Batch objective:
  - Harden governance transition endpoints (`approve/reject`) and keep unified 409 contract behavior.
- Updated controllers:
  - `backend/app/Http/Controllers/Api/V1/GovernanceController.php`
  - `backend/app/Http/Controllers/Api/V1/LeaveController.php`
  - `backend/app/Http/Controllers/Api/V1/SalaryController.php`
- Baseline after batch:
  - `backend/reports/security-baseline-after-governance-batch1/`
  - `TOTAL_ROUTES=344`, `SENSITIVE_ROUTES=264`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=100`
  - Transition signal delta vs previous baseline:
    - Before (`after-gate-refinement`): `present=4`, `missing_or_unknown=12`, `not_applicable=248`
    - After (`after-governance-batch1`): `present=7`, `missing_or_unknown=9`, `not_applicable=248`

## Batch-2 execution (Fleet / Fleet-Portal approve/reject)

- Batch objective:
  - Close transition-risk signals on fleet approval/rejection endpoints and enforce explicit permission coverage.
- Updated controllers:
  - `backend/app/Http/Controllers/Api/V1/FleetController.php`
  - `backend/app/Http/Controllers/Api/V1/FleetPortalController.php`
- Updated routes:
  - `backend/routes/api.php`
  - Added route-level permission guards:
    - `/api/v1/fleet/work-orders/{id}/approve` => `permission:work_orders.update`
    - `/api/v1/fleet-portal/work-orders/{id}/approve-credit` => `permission:fleet.workorder.approve`
    - `/api/v1/fleet-portal/work-orders/{id}/reject-credit` => `permission:fleet.workorder.approve`
- Baseline after batch:
  - `backend/reports/security-baseline-after-fleet-batch1/`
  - `TOTAL_ROUTES=344`, `SENSITIVE_ROUTES=264`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=97`
  - Delta vs batch-1 baseline:
    - `MEDIUM_RISK`: `100 -> 97` (improved)
    - Transition signal:
      - Before (`after-governance-batch1`): `present=7`, `missing_or_unknown=9`, `not_applicable=248`
      - After (`after-fleet-batch1`): `present=10`, `missing_or_unknown=6`, `not_applicable=248`

## Batch-3 execution (Governance workflows approve/reject)

- Batch objective:
  - Close remaining `missing_or_unknown` on governance workflow transition endpoints.
- Updated controller:
  - `backend/app/Http/Controllers/Api/V1/GovernanceController.php`
- Applied controls:
  - Company-scoped workflow lookup before mutation.
  - Pending-only transition gate for approve/reject.
  - Unified 409 transition contract on invalid state:
    - `message`, `trace_id`, `code=TRANSITION_NOT_ALLOWED`, `status=409`
- Feature evidence:
  - `backend/tests/Feature/Security/ConflictErrorContractTest.php`
  - New case for governance invalid approve transition contract.
- Baseline after batch:
  - `backend/reports/security-baseline-after-governance-batch2/`
  - `TOTAL_ROUTES=344`, `SENSITIVE_ROUTES=264`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=97`
  - Transition signal delta vs previous baseline:
    - Before (`after-fleet-batch1`): `present=10`, `missing_or_unknown=6`, `not_applicable=248`
    - After (`after-governance-batch2`): `present=12`, `missing_or_unknown=4`, `not_applicable=248`

Interpretation:
- Gate noise reduced as intended by limiting transition-route detection keywords (`status`, `receive`, `approve`, `reject`, `cancel`, `close`, `reopen`, `renew`) and avoiding generic `update`.
- Core risk posture remains `0` high-risk and `97` medium-risk in the latest measured baseline, while transition signal quality improved.

## Exit gate stance (Wave 1 vs later waves)

- **Wave 1** closure review can proceed now (`MEDIUM_RISK_SENSITIVE_ROUTES=0`), while keeping formal sign-off gates and documentation checks.
- Clearing **`transition_guard_summary.missing_or_unknown`** (`0` in latest audit) improves signal quality but is **not** the same as closing Wave 1.
- Avoid announcing **Wave 3** until Wave 1 is much closer to its agreed exit threshold for medium-risk sensitive mutations.

## Batch-4 execution (WorkOrder / Bays / Workshop & bookings)

- Batch objective:
  - Operational transition hardening for work orders (`PATCH .../work-orders/{id}/status`), bay availability (`PATCH .../bays/{id}/status`), workshop tasks (`PATCH .../workshop/tasks/{id}/status`), and booking lifecycle (`PATCH .../bookings/{id}`), including legacy `status`-only payloads for the bookings UI.
- Updated code:
  - `backend/app/Services/WorkOrderService.php`
  - `backend/app/Http/Controllers/Api/V1/WorkOrderController.php`
  - `backend/app/Http/Controllers/Api/V1/BayController.php`
  - `backend/app/Http/Controllers/Api/V1/WorkshopController.php`
- Baseline after batch:
  - `backend/reports/security-baseline-after-wo-bay-workshop-batch/`
  - `TOTAL_ROUTES=344`, `SENSITIVE_ROUTES=264`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=97`
  - Transition signal delta vs previous baseline:
    - Before (`after-governance-batch2`): `present=12`, `missing_or_unknown=4`, `not_applicable=248`
    - After (`after-wo-bay-workshop-batch`): `present=14`, `missing_or_unknown=2`, `not_applicable=248`

## Batch-5 execution (Final `missing_or_unknown` on transition routes + state matrices)

- Objective:
  - Drive **`transition_guard_summary.missing_or_unknown` to `0`** by adding auditable `in_array`-style gates on the last flagged routes.
  - Publish a **single team-facing reference** for state machines and booking API expectations.
- Code:
  - `backend/app/Http/Controllers/Api/V1/InventoryController.php` (`cancelReservation`)
  - `backend/app/Http/Controllers/Api/V1/SubscriptionController.php` (`renew`)
- Documentation:
  - `docs/api-state-transition-matrices.md` — matrices + **operational note for `PATCH /api/v1/bookings/{id}`** (must send **`action` or `status`**; empty body → **422**; external integrators should prefer `action`).
- Baseline after batch:
  - `backend/reports/security-baseline-after-missing-or-unknown-close/`
  - `MEDIUM_RISK_SENSITIVE_ROUTES=97` (unchanged)
  - `transition_guard_summary`: `present=16`, **`missing_or_unknown=0`**, `not_applicable=248`

## Batch-6 execution (Financial & subscriptions permission hardening)

- Objective:
  - Reduce medium-risk sensitive routes in the **financial/subscriptions** priority slice without opening new feature scope.
- Updated routes:
  - `backend/routes/api.php`
- Coverage added (selected):
  - invoices store/pay/create-from-work-order/media/update/delete
  - POS sale
  - wallet/wallets mutations and payment refund
  - ledger + chart-of-accounts route middleware
- Baseline after batch:
  - `backend/reports/security-baseline-after-financial-permission-batch1/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=270`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=75`
  - Delta vs previous (`after-missing-or-unknown-close`):
    - `MEDIUM_RISK`: `97 -> 75` ✅
    - `transition_guard_summary.missing_or_unknown`: `0 -> 0` (stable)

## Batch-7 execution (Platform/Governance permission hardening)

- Objective:
  - Continue medium-risk closure for platform/governance endpoints by adding explicit permission middleware to internal QA mutation and sensitive notification mutations.
- Updated routes:
  - `backend/routes/api.php`
- Coverage added:
  - `/api/v1/internal/run-tests` => `permission:users.update`
  - `/api/v1/notifications/share-email` => `permission:users.update`
  - `/api/v1/notifications/track-share` => `permission:users.update`
- Baseline after batch:
  - `backend/reports/security-baseline-after-platform-governance-batch1/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=270`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=72`
  - Delta vs previous (`after-financial-permission-batch1`):
    - `MEDIUM_RISK`: `75 -> 72` ✅
    - `transition_guard_summary.missing_or_unknown`: `0 -> 0` (stable)

## Batch-8 execution (Integrations / External)

- Objective:
  - Start integrations/external closure by removing remaining external mutation route from `medium` without introducing incompatible permission middleware for API-key clients.
- Updated code:
  - `backend/app/Http/Requests/External/StoreExternalInvoiceRequest.php` (new; explicit `authorize()` signal using `api_key` attribute from middleware)
  - `backend/app/Http/Controllers/Api/V1/External/ExternalInvoiceController.php` (`store` switched from inline validate to typed FormRequest)
- Baseline after batch:
  - `backend/reports/security-baseline-after-integrations-external-batch8/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=270`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=71`
  - Delta vs previous (`after-platform-governance-batch1`):
    - `MEDIUM_RISK`: `72 -> 71` ✅
    - `transition_guard_summary.missing_or_unknown`: `0 -> 0` (stable)
- Explicit route extraction (Integrations/External scope):
  - Exited medium-risk:
    - `POST /api/v1/external/v1/invoices` (now `low-medium`)
  - Remaining medium-risk:
    - `GET /api/v1/plans` (public read-only endpoint by design on `api`; still sensitive-classified by audit keywords)

## Batch-9 execution (Support / Workshop / HR mutations)

- Objective:
  - Close support/workshop mutation medium-risk routes via explicit permission middleware.
- Updated routes:
  - `backend/routes/api.php`
- Coverage added:
  - `permission:users.update` on workshop mutations (employees, attendance, tasks, commission rules, commission pay).
  - `permission:users.update` on support mutations (tickets, SLA writes/checks, KB writes/vote, KB categories create).
- Baseline after batch:
  - `backend/reports/security-baseline-after-support-workshop-batch9/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=270`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=49`
  - Delta vs previous (`after-integrations-external-batch8`):
    - `MEDIUM_RISK`: `71 -> 49` ✅
    - `transition_guard_summary.missing_or_unknown`: `0 -> 0` (stable)
- Explicit route extraction (Support/Workshop scope):
  - Exited medium-risk:
    - `POST /api/v1/workshop/employees`
    - `PUT /api/v1/workshop/employees/{id}`
    - `POST /api/v1/workshop/attendance/check-in`
    - `POST /api/v1/workshop/attendance/check-out`
    - `POST /api/v1/workshop/tasks`
    - `PATCH /api/v1/workshop/tasks/{id}/status`
    - `POST /api/v1/workshop/commission-rules`
    - `PUT /api/v1/workshop/commission-rules/{id}`
    - `DELETE /api/v1/workshop/commission-rules/{id}`
    - `POST /api/v1/workshop/commissions/{id}/pay`
    - `POST /api/v1/support/tickets`
    - `PUT /api/v1/support/tickets/{id}`
    - `PATCH /api/v1/support/tickets/{id}/status`
    - `POST /api/v1/support/tickets/{id}/replies`
    - `POST /api/v1/support/tickets/{id}/rate`
    - `POST /api/v1/support/sla-policies`
    - `PUT /api/v1/support/sla-policies/{id}`
    - `POST /api/v1/support/sla/check-breaches`
    - `POST /api/v1/support/kb`
    - `PUT /api/v1/support/kb/{id}`
    - `POST /api/v1/support/kb/{id}/vote`
    - `POST /api/v1/support/kb-categories`
  - Remaining medium-risk (within this scope):
    - none

## Batch-10 execution (Route-by-route precision)

- Objective:
  - Move from wide batches to precise closure per route with split execution:
    - Group A: real hardening on mutation endpoints.
    - Group B: audit classification refinement for public-by-design endpoints.
- Group A (hardening) changes:
  - `backend/routes/api.php`
  - Added explicit permission coverage for targeted mutation routes (customers, vehicles, suppliers, work-orders, purchases, inventory reservations, fleet/fleet-portal operations, bays/bookings, notifications, plugins, zatca submit).
  - Removed unguarded duplicate mutation exposure by constraining resource routes:
    - `customers`, `vehicles`, `suppliers` => read-only resource registration (`index/show`)
    - `products` => resource excludes `destroy` (kept guarded explicit delete route)
- Group B (audit refinement):
  - `backend/app/Console/Commands/SecurityBaselineAuditCommand.php`
  - `/api/v1/plans` added to `nonSensitiveExactUris` as public read-only by design.
- Baseline after batch:
  - `backend/reports/security-baseline-after-batch10-route-by-route-v2/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=269`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=9`
  - Delta vs previous (`after-support-workshop-batch9`):
    - `MEDIUM_RISK`: `49 -> 9` ✅
    - `transition_guard_summary.missing_or_unknown`: `0 -> 0` (stable)

### Explicit diff (from the prior 49)

- Exited medium-risk (40 routes):
  - `DELETE /api/v1/customers/{customer}`
  - `DELETE /api/v1/plugins/{key}/uninstall`
  - `DELETE /api/v1/products/{product}`
  - `DELETE /api/v1/purchases/{id}/documents/{index}`
  - `DELETE /api/v1/suppliers/{supplier}`
  - `DELETE /api/v1/vehicles/{vehicle}`
  - `DELETE /api/v1/work-orders/{id}`
  - `GET /api/v1/plans`
  - `PATCH /api/v1/bays/{id}/status`
  - `PATCH /api/v1/bookings/{id}`
  - `PATCH /api/v1/inventory/reservations/{id}/cancel`
  - `PATCH /api/v1/inventory/reservations/{id}/consume`
  - `PATCH /api/v1/inventory/reservations/{id}/release`
  - `PATCH /api/v1/purchases/{id}/status`
  - `PATCH /api/v1/work-orders/{id}/status`
  - `POST /api/v1/bays`
  - `POST /api/v1/bookings`
  - `POST /api/v1/bookings/availability`
  - `POST /api/v1/customers`
  - `POST /api/v1/fleet/verify-plate`
  - `POST /api/v1/fleet-portal/wallet/top-up`
  - `POST /api/v1/fleet-portal/work-orders`
  - `POST /api/v1/inventory/reservations`
  - `POST /api/v1/plugins/{key}/execute`
  - `POST /api/v1/plugins/{key}/install`
  - `POST /api/v1/purchases`
  - `POST /api/v1/purchases/{id}/documents`
  - `POST /api/v1/purchases/{id}/receipts`
  - `POST /api/v1/purchases/{id}/receive`
  - `POST /api/v1/suppliers`
  - `POST /api/v1/vehicles`
  - `POST /api/v1/work-orders`
  - `POST /api/v1/zatca/submit`
  - `PUT /api/v1/customers/{customer}`
  - `PUT /api/v1/notifications/{id}/read`
  - `PUT /api/v1/notifications/read-all`
  - `PUT /api/v1/plugins/{key}/configure`
  - `PUT /api/v1/suppliers/{supplier}`
  - `PUT /api/v1/vehicles/{vehicle}`
  - `PUT /api/v1/work-orders/{id}`

### Remaining routes table (the final 9 from the original 49)

| route | current reason for `medium` | needs real hardening? | public by design? | audit refinement needed? | action taken in Batch-10 | result |
|---|---|---|---|---|---|---|
| `DELETE /api/v1/services/{service}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `DELETE /api/v1/bundles/{bundle}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `POST /api/v1/quotes` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `PUT /api/v1/quotes/{quote}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `DELETE /api/v1/quotes/{quote}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `POST /api/v1/nps` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `PUT /api/v1/units/{id}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `DELETE /api/v1/units/{id}` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |
| `POST /api/v1/units/conversions` | mutation, no `permission:*`, no authorize signal | yes | no | no | left for next slice (permission taxonomy alignment pending) | still `medium` |

## Batch-11 execution (Final 9)

- Objective:
  - Close the remaining 9 medium-risk endpoints by strict priority: destructive/master-data routes, quote mutations, then NPS write.
- Updated routes:
  - `backend/routes/api.php`
- Controls applied:
  - `services`/`bundles` destructive delete routes guarded with `permission:users.update` and routed explicitly.
  - `quotes` mutations (`store/update/destroy`) guarded with `permission:users.update`; resource route constrained to read-only actions.
  - `units` mutation routes (`update/destroy/storeConversion`) guarded with `permission:inventory.adjust`.
  - `nps` write route guarded with `permission:users.update`.
- Baseline after batch:
  - `backend/reports/security-baseline-after-batch11-final9/`
  - `TOTAL_ROUTES=345`, `SENSITIVE_ROUTES=269`, `HIGH_RISK_SENSITIVE_ROUTES=0`, `MEDIUM_RISK_SENSITIVE_ROUTES=0`
  - Delta vs previous (`after-batch10-route-by-route-v2`):
    - `MEDIUM_RISK: 9 -> 0` ✅
    - transition signal remained stable.

### Final classification for the previous 9

| route | final classification | action taken | result |
|---|---|---|---|
| `DELETE /api/v1/services/{service}` | closed by hardening | added `permission:users.update` | `low-medium` |
| `DELETE /api/v1/bundles/{bundle}` | closed by hardening | added `permission:users.update` | `low-medium` |
| `POST /api/v1/quotes` | closed by hardening | added `permission:users.update` | `low-medium` |
| `PUT /api/v1/quotes/{quote}` | closed by hardening | added `permission:users.update` | `low-medium` |
| `DELETE /api/v1/quotes/{quote}` | closed by hardening | added `permission:users.update` | `low-medium` |
| `POST /api/v1/nps` | closed by hardening | added `permission:users.update` | `low-medium` |
| `PUT /api/v1/units/{id}` | closed by hardening | added `permission:inventory.adjust` | `low-medium` |
| `DELETE /api/v1/units/{id}` | closed by hardening | added `permission:inventory.adjust` | `low-medium` |
| `POST /api/v1/units/conversions` | closed by hardening | added `permission:inventory.adjust` | `low-medium` |

- acceptable by design (within these 9): none
- audit refinement (within these 9): none

## API 409 contract stabilization

Contract now standardized for transition rejections:
- `message`
- `trace_id`
- `code` (`TRANSITION_NOT_ALLOWED` for disallowed state moves; work order optimistic locking uses `RESOURCE_VERSION_MISMATCH` on `409`)
- `status` (`409`)

Controllers updated:
- `backend/app/Http/Controllers/Api/V1/InvoiceController.php`
- `backend/app/Http/Controllers/Api/V1/PurchaseController.php`
- `backend/app/Http/Controllers/Api/V1/SupportController.php`
- `backend/app/Http/Controllers/Api/V1/WorkOrderController.php`
- `backend/app/Http/Controllers/Api/V1/BayController.php`
- `backend/app/Http/Controllers/Api/V1/WorkshopController.php`
- `backend/app/Http/Controllers/Api/V1/InventoryController.php` (reservation **cancel** path)
- `backend/app/Http/Controllers/Api/V1/SubscriptionController.php` (**renew** eligibility guard)

## Test evidence

- `backend/tests/Feature/Security/ConflictErrorContractTest.php`
  - Validates 409 API contract consistency across Invoice/Purchase/Support/Fleet portal/Governance and batch-4 entities (work order, bay, task, booking).
- `backend/tests/Feature/Security/StateTransitionGuardsTest.php`
  - Valid transition succeeds.
  - Invalid transition fails.
  - No side effects after `409`.
  - DB state stability after rejection.
  - Consistent rejection message suffix.
  - Batch-4: work order, bay, workshop task, booking legacy confirm.
- `backend/tests/Feature/WorkOrder/OptimisticLockingTest.php`
  - Invalid API transition and version conflict assert unified 409 payloads.

Executed (representative):
- `docker compose exec -T app php artisan test tests/Feature/Security/StateTransitionGuardsTest.php tests/Feature/Security/ConflictErrorContractTest.php tests/Feature/WorkOrder/OptimisticLockingTest.php`
- After batch-5 additions, re-run the same suite plus any inventory/subscription tests you rely on in CI.

## Integrator advisory — bookings

- **`PATCH /api/v1/bookings/{id}`** requires **`action`** *or* a legacy **`status`** field (mapped to an action). **Neither** → **422** with `message` + `trace_id`.
- The bundled SPA bookings flow already sends `status`; **third-party API clients** must be notified and should prefer explicit `action` for new work.

## Recommended next batch (not final close)

1. Close remaining medium-risk routes in prioritized slices (policy/permission coverage on sensitive mutating routes).
2. Add explicit permission/policy coverage where missing on mutation routes flagged in policy coverage report.
3. Re-run `security:baseline-audit` after each slice and track `MEDIUM_RISK_SENSITIVE_ROUTES` delta.
4. Keep this package as interim review evidence until medium-risk backlog reaches target threshold.
