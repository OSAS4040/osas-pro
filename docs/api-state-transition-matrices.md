# API state transition matrices (Wave 1 hardening)

Reference for integrators and internal teams. Invalid transitions return **409** with JSON body:

`message`, `trace_id`, `code` (usually `TRANSITION_NOT_ALLOWED`; work order **version conflict** uses `RESOURCE_VERSION_MISMATCH`), `status` (409).

Where only **422** is documented below, the payload is validation or missing-parameter (not the unified 409 contract).

---

## Bookings — `PATCH /api/v1/bookings/{id}`

**Contract**

- The body must include **either** `action` **or** a legacy **`status`** field (target state). Sending **neither** returns **422** with a clear `message` and `trace_id`.
- **Do not send both** `action` and `status`; that returns **422**.

**`action` values**

| `action`   | Allowed from statuses      | Result / notes        |
|-----------|----------------------------|------------------------|
| `confirm` | `pending` (idempotent if already `confirmed`) | → `confirmed` |
| `start`   | `confirmed` (idempotent if already `in_progress`) | → `in_progress` |
| `complete`| `in_progress` (idempotent if already `completed`) | → `completed` |
| `cancel`  | `pending`, `confirmed`, `in_progress` (idempotent if already `cancelled`) | → `cancelled` |

**Legacy `status` (UI compatibility)**

Mappings to the same machine: `confirmed` → confirm, `in_progress` → start, `completed` → complete, `cancelled` → cancel.

**External API clients**

- Prefer explicit `action` in new integrations.
- If you previously sent an empty body expecting a no-op, that is **no longer supported**; always send `action` or mappable `status`.

---

## Work orders — `PATCH /api/v1/work-orders/{id}/status`

Source of truth: `WorkOrder::canTransitionTo()` / `WorkOrderService::transition()`.

| From          | Allowed targets |
|---------------|-----------------|
| `draft`       | `pending` |
| `pending`     | `in_progress`, `cancelled` |
| `in_progress` | `on_hold`, `completed`, `cancelled` |
| `on_hold`     | `in_progress`, `cancelled` |
| `completed`   | `delivered` |
| `delivered`   | _(terminal)_ |
| `cancelled`   | _(terminal)_ |

Stale `version` → **409** + `RESOURCE_VERSION_MISMATCH`.

---

## Bays — `PATCH /api/v1/bays/{id}/status`

Statuses: `available`, `reserved`, `in_use`, `maintenance`, `out_of_service`.

| From               | Allowed targets (including self / no-op) |
|--------------------|------------------------------------------|
| `available`        | available, reserved, in_use, maintenance, out_of_service |
| `reserved`         | reserved, available, in_use, maintenance, out_of_service |
| `in_use`           | in_use, available, maintenance, out_of_service |
| `maintenance`      | maintenance, available, out_of_service |
| `out_of_service`   | out_of_service, maintenance, available |

---

## Workshop tasks — `PATCH /api/v1/workshop/tasks/{id}/status`

Send **`action`** *or* **`status`**, not both.

**Direct `status` patch** — allowed moves:

| From           | Allowed targets |
|----------------|-----------------|
| `pending`      | pending, assigned, in_progress, cancelled |
| `assigned`     | assigned, pending, in_progress, cancelled |
| `in_progress`  | in_progress, completed, cancelled, review |
| `review`       | review, completed, in_progress |
| `completed`    | completed only |
| `cancelled`    | cancelled only |

**Actions**

- `start`: from `pending` or `assigned` → `in_progress`
- `complete`: from `in_progress` or `review` → `completed`
- `assign`: from `pending`, `assigned`, or `in_progress`; requires `employee_id`

---

## Inventory reservations — `PATCH /api/v1/inventory/reservations/{id}/cancel`

Mutating inventory API routes (including this `PATCH`) require the **`Idempotency-Key`** request header under `financial.protection` (see `FinancialOperationProtectionMiddleware`).

Cancel is allowed **only** from `pending`. Model-level map (`InventoryReservation::canTransitionTo`):

| From     | cancel |
|---------|--------|
| `pending` | yes |
| `consumed`, `released`, `canceled`, `expired` | no |

`release` / `consume` endpoints enforce transitions inside `ReservationService` (domain exceptions may still be normalized to **409** on cancel).

---

## Subscriptions — `POST /api/v1/subscriptions/renew`

If a subscription row exists for the company, renew is allowed only when its status is one of: `active`, `grace_period`, `suspended`. Otherwise **409** with transition-style message.

Creating a first subscription when none exists (or after guard passes) follows existing renewal logic (prior record suspended when replaced).

---

## Other entities (already documented in code / tests)

- Invoices, purchases (`receive`), support tickets, governance workflows, leaves, salaries, fleet approvals: see controllers and `tests/Feature/Security/*` for examples.
