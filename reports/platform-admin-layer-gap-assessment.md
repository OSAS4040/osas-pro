# Platform Administration Layer — Gap Assessment

Execution Status: Deferred until Pilot evidence or first paid customer

## Goal

Assess the gap between the current state (tenant-based SaaS) and the target state (full SaaS platform with centralized administration).

## Current State

- The system runs as a multi-tenant SaaS platform.
- Users are tied to `company_id` in practice.
- No dedicated platform user layer exists.
- Permissions are defined in tenant/company context.
- No separate platform access layer is available.

## Core Gaps

### 1) Platform Users

- Missing roles:
  - `super_admin`
  - `platform_admin`
  - `platform_support`
- No explicit platform-only users independent from tenant context.

### 2) Separation of Concerns (Platform vs Tenant)

- No explicit architectural split between platform administration and tenant operations.
- Runtime access model is primarily tenant-context driven.

### 3) Platform Permissions Namespace

- Missing dedicated namespace such as:
  - `platform.*`

### 4) Access Layer

- Missing dedicated API namespace:
  - `/api/platform/*`
- Missing dedicated platform middleware boundary.

### 5) Central Management Capabilities

- Company and subscription controls exist partially.
- These controls are still tied to tenant-oriented policies and flows.

## Risks If Deferred Too Long

- Harder centralized lifecycle management for tenants.
- Potential role overlap/confusion between platform and tenant responsibilities.
- Scalability friction in support/operations/governance at platform level.
- Reduced operational clarity for cross-tenant monitoring and intervention.

## Recommendation

- Keep implementation deferred now (no heavy engineering before market signal).
- Approve blueprint and readiness package now.
- Start implementation only after:
  - Pilot usage evidence, or
  - First paid customer.

## Governance Fields

- Prepared by: ____________________
- Reviewed by: ____________________
- Approved by: ____________________
- Date: ____________________

