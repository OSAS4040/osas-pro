# Work Order Pricing Governance

## Purpose

This document defines the official pricing and authorization governance model for Work Orders in Osas Pro (`أسس برو`).  
It is the implementation reference for backend services, API contracts, and Fleet Portal behavior.

## Scope

- Service-linked Work Order line pricing (`service_id` lines).
- Fleet customer workflow for creating Work Orders.
- Company-level pricing policy ownership and tenant isolation.
- Snapshot persistence of resolved pricing decisions.

## Governance Roles

- **Platform (`أسس Platform`)**
  - Provides the governance framework, not per-customer line pricing operations.
  - Must not override beneficiary company pricing policy decisions during order creation.
- **Beneficiary Company**
  - Owns pricing policies and customer classifications.
  - Manages customer groups, policy hierarchy, and activation windows.
- **End Customer (Fleet Portal)**
  - Can request service creation only.
  - Cannot set or override unit prices/tax for service-linked lines.

## Commercial Contract Catalog (`contract_service_items`)

When a customer is linked to an **active** commercial contract (`customers.pricing_contract_id` → `contracts` within effective dates):

- Allowed services and their **contractual unit price**, tax override, discounts, branch scope, vehicle scope, **usage cap** (`max_total_quantity`), and optional approval flags are defined per row in `contract_service_items` (API: `GET/POST/PUT/DELETE /api/v1/governance/contracts/{contract}/service-items`).
- This layer is the **governance filter over the company catalog**: it does not replace the master service catalog; it constrains what the customer may order and at what price.

## Pricing Resolution Order

For each Work Order line with `service_id`, the server resolves price in this strict order:

1. **Contract line item** (`contract_service_items`) when the customer has an active pricing contract, the service appears on the contract, and branch/vehicle scope matches.
2. Customer-specific policy (`service_pricing_policies`)
3. Customer-group policy
4. Contract-linked policy row (legacy `service_pricing_policies` of type `contract`)
5. General policy
6. Service base price fallback

**Fleet-origin** requests: if the customer has an active pricing contract but **no matching contract line** (service not contracted, wrong vehicle, or wrong branch scope), the request is **rejected** — the fleet user cannot fall through to list/catalog pricing.

Non-fleet (workshop) users without a matching contract line **may** fall through to `service_pricing_policies` and base price (backward compatibility for tenants migrating data).

Within `service_pricing_policies` at the same level:

- Higher `priority` wins.
- If `priority` ties, latest `effective_from` wins.

Contract line rows are ordered by **`priority` DESC**, then **`id` DESC**, when multiple rows could match.

## Security Controls

- Server-side resolution is mandatory for service-linked lines.
- Fleet-origin Work Orders require `service_id`; missing `service_id` is rejected.
- Client-provided `unit_price`/`tax_rate` for service-linked lines are ignored.
- Invalid or missing approved pricing returns a controlled business error.
- Policies are scoped by `company_id` to enforce tenant isolation.

## Data Model and Snapshot Fields

Work Order item snapshots persist:

- `pricing_source`
- `pricing_policy_id` (when resolved via `service_pricing_policies`)
- `pricing_contract_service_item_id` (when resolved via `contract_service_items`)
- `pricing_resolved_at`
- `pricing_resolved_by_system`
- `pricing_notes`

These fields provide auditability and protect against future policy drift.

## Fleet Portal UX Rules

- User selects **vehicle first**; the service catalog is loaded with `GET /fleet-portal/service-catalog?vehicle_id=...` so only **contract-allowed** services for that asset/branch appear when a pricing contract is in effect.
- Unit price and tax are displayed as read-only preview values from `POST /fleet-portal/work-orders/pricing-preview` (include `vehicle_id`).
- Price source label is shown to the user.
- Submission is blocked if no approved price/quota can be resolved.

## Feature Gating

- `work_order_advanced_pricing` controls advanced hierarchy behavior.
- If disabled for tenant plan, the resolver falls back to service base price.

## API/Service Contracts (Implemented)

- `GET /api/v1/governance/contracts/{contract}/service-items` — list contractual catalog lines.
- `POST|PUT|DELETE /api/v1/governance/contracts/{contract}/service-items` — maintain lines (governance permission bundle).
- `GET /api/v1/fleet-portal/service-catalog?vehicle_id=` — filtered to contractual lines when a pricing contract applies (vehicle + branch scope).
- `POST /api/v1/fleet-portal/work-orders/pricing-preview` — `service_id` + **`vehicle_id`** for scoped resolution.
- Fleet Work Order creation enforces the same resolver rules server-side.
- Work Order compilation snapshots `pricing_contract_service_item_id` or `pricing_policy_id` as applicable.

## Validation and Test Coverage

Implemented feature tests cover governance-critical scenarios:

1. Contract line precedence over `service_pricing_policies` when both exist.
2. Customer-specific policy when no contract line matches (workshop / no fleet strict path).
3. Customer-group resolution when customer-specific is absent.
4. Legacy contract policy row resolution when contract lines do not apply.
5. General policy resolution when specific scopes are absent.
6. Service base fallback when no policy matches.
7. Tie-break rules by priority and effective date.
8. Manual override prevention for service-linked lines.
9. Snapshot persistence on Work Order items (`pricing_policy_id` / `pricing_contract_service_item_id`).
10. Tenant isolation between companies.
11. Advanced-pricing-disabled fallback to service base.
12. Fleet strict rejection when service is off-contract.
13. Vehicle-scoped contractual lines.
14. Usage cap (`max_total_quantity`) per contract line.

## Operational Acceptance Checklist

- Run database migrations in all environments.
- Run backend test suite including pricing governance feature tests.
- Validate Fleet Portal order form read-only pricing behavior in UI.
- Confirm `pricing_source`, `pricing_policy_id` / `pricing_contract_service_item_id`, and related snapshot fields are present on Work Order items.
- Seed **contract service items** for each active customer contract before expecting fleet users to see a non-empty catalog.
- Verify no cross-tenant policy leakage by company context switching tests.

