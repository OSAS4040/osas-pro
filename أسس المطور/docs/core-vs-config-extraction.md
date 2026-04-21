# Core vs Config Extraction (Batch-1 / Batch-2 / Batch-3)

Date: 2026-04-01
Phase: Multi-Vertical Configurable Core

## Shared Core (non-configurable behavior)

- Security and permission enforcement pipeline.
- Financial reconciliation execution controls and reliability guardrails.
- Approval workflow state machine integrity (`pending|approved|rejected|cancelled`).
- Meeting/action audit trail consistency (`trace_id`, actor, timestamps).
- Idempotency and concurrency safety contracts.

## Configurable Surface (extract to settings)

- Feature toggles by vertical/company/branch (`booking.enabled`, `fleet.portal.enabled`).
- Operational defaults (`booking.auto_confirm`, `approval.strict`).
- UI/flow enablement flags (without introducing new dashboard scope).
- Plan/vertical capability constraints.

## Configuration Hierarchy

Resolution order (lowest to highest precedence):
1. `system`
2. `plan`
3. `vertical`
4. `company`
5. `branch`

Branch-level values override all other scopes.

## Baseline Vertical Profiles

- `service_workshop`
- `fleet_operations`
- `retail_pos`

Profiles are intentionally minimal in Batch-1 and serve as anchors for controlled expansion.

## Batch-2 Wired Operational Use Cases

Batch-2 converted the config layer from structure-only to first live operational behavior with bounded scope:

- `quotes.enabled` (Retail POS paths)
- `wallet.enabled` (Retail POS/Fleet wallet paths)
- `work_orders.require_bay_assignment` (Service Workshop)
- `bookings.enabled` (Service Workshop booking flow)
- `fleet.approval_required` (Fleet verify-plate flow)
- `pos.quick_sale_enabled` (Retail POS quick sale flow)

### Seeded Operational Settings

Baseline keys are seeded in `config_settings` at these scopes:

- `system`
- `plan`
- `vertical`
- `company`
- `branch`

Initial key set:

- `inventory.require_reservation`
- `inventory.allow_negative_stock`
- `work_orders.require_bay_assignment`
- `bookings.enabled`
- `quotes.enabled`
- `wallet.enabled`
- `fleet.approval_required`
- `pos.quick_sale_enabled`

### Live Precedence Proof

Resolver precedence remains:

`system -> plan -> vertical -> company -> branch`

Feature tests in Batch-2 verify that the deepest available scope wins for effective behavior.

## Batch-3 Profile Assignment + Effective Visibility

Batch-3 adds lightweight operational control and visibility, without dashboards:

- Assign/update `vertical_profile_code` for:
  - `companies`
  - `branches`
- Read-only effective config visibility endpoints:
  - `GET /api/v1/companies/{id}/effective-config`
  - `GET /api/v1/branches/{id}/effective-config`
- Profile assignment endpoints:
  - `PATCH /api/v1/companies/{id}/vertical-profile`
  - `PATCH /api/v1/branches/{id}/vertical-profile`

### Batch-3 Verification Scope

- Company override proof.
- Branch override proof.
- Vertical fallback proof.
- Live behavior change after profile assignment.
