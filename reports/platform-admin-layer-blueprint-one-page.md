# Platform Administration Layer — Blueprint (One Page)

Execution Status: Deferred until Pilot evidence or first paid customer

## Objective

Build a centralized platform administration layer fully separated from tenant context, without breaking existing tenant runtime behavior.

## 1) Platform Users

Introduce dedicated platform roles:

- `super_admin`
- `platform_admin`
- `platform_support`
- `platform_ops`
- `platform_billing`
- `platform_sales`

Design rule:

- Platform users are managed separately from tenant users.
- Platform users are not treated as regular tenant operators.

## 2) Permissions Namespace

Add platform-level permissions namespace:

- `platform.companies.manage`
- `platform.subscriptions.manage`
- `platform.plans.manage`
- `platform.support.view`
- `platform.users.manage`

## 3) Access Layer

Create dedicated API namespace:

- `/api/platform/*`

Create dedicated middleware boundary:

- `EnsurePlatformUser`

This boundary must not depend on tenant middleware for platform endpoints.

## 4) Core Capabilities

### Companies Management

- Create company
- Activate/suspend company
- Centralized company list and status visibility

### Subscriptions and Plans

- Plan management
- Attach company to plan
- Activate/pause subscription lifecycle
- Enforce plan limits centrally

### Support and Operations

- Cross-tenant operational visibility
- Issue tracking and controlled intervention
- Strict permission-based restricted access

## 5) Isolation Model

- `Platform Users` and `Tenant Users` are separate identities and scopes.
- Platform endpoints do not apply tenant company scoping by default.
- Tenant endpoints remain unchanged and isolated.

## 6) Security and Governance Rules

- Deny direct tenant data access from platform context unless explicit policy allows it.
- Audit all platform-level actions with traceability.
- Define and enforce role scopes clearly to avoid privilege overlap.

## Implementation Principle

- No change to core tenant runtime behavior at this stage.
- No change to financial/accounting flows as part of this blueprint.
- Build as an additive layer only, activated in a controlled rollout later.

## Governance Fields

- Prepared by: ____________________
- Reviewed by: ____________________
- Approved by: ____________________
- Date: ____________________

