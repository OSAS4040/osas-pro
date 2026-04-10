# Wave 2 State Enforcement Progress

## Scope started

- Wave 2 launch started with HR Leaves transition enforcement.
- Target principle: block non-authorized state transitions at controller/service layer.

## Implemented change

- File updated: `backend/app/Http/Controllers/Api/V1/LeaveController.php`
- Changes:
  - `approve()` now only transitions from `pending -> approved`
  - `reject()` now only transitions from `pending -> rejected`
  - Non-eligible transitions now return HTTP `409` with clear message

## Operational impact

- Prevents repeated/invalid leave state overrides (e.g., approving already approved/rejected records).
- Enforces deterministic workflow behavior for leave requests.

## Current transition matrix (Leaves)

- Allowed:
  - `pending -> approved`
  - `pending -> rejected`
- Denied:
  - `approved -> approved`
  - `approved -> rejected`
  - `rejected -> approved`
  - `rejected -> rejected`

## Related guard status (from latest Wave-1 baseline run)

- Baseline dir: `backend/reports/security-baseline-20260331-205223`
- `HIGH_RISK_SENSITIVE_ROUTES=0`
- `MEDIUM_RISK_SENSITIVE_ROUTES=100`

## Next Wave-2 targets

1. Invoice status transition normalization.
2. Purchase / goods receipt transition hardening.
3. Support ticket transition matrix enforcement.
4. Standardized state error contract (409 + reason code/message).

