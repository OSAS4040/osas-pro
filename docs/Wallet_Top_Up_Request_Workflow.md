# Wallet top-up request workflow

## Overview

Users submit a **wallet top-up request** (amount, payment method, optional reference, optional customer notes, receipt when required). The request is stored as **`pending`** and **does not change wallet balance**.

After review, staff with **`wallet.top_up_requests.review`** may:

- **Approve** — credits the **customer main / fleet wallet** via the existing `WalletService` and stores `approved_wallet_transaction_id`. Idempotent: repeating approve does not double-credit.
- **Reject** — status `rejected`, balance unchanged.
- **Return for revision** — status `returned_for_revision`, balance unchanged; the requester may edit and **resubmit** (`pending` again).

## States

| Status | Meaning |
|--------|---------|
| `pending` | Awaiting review; no balance change. |
| `approved` | Credited once; linked wallet transaction id set. |
| `rejected` | Closed; no credit. |
| `returned_for_revision` | Requester may update and resubmit. |

## When balance is credited

**Only on approve**, inside a DB transaction, using the existing wallet top-up paths (`topUpIndividual` / `topUpFleet`) with reference metadata pointing at `WalletTopUpRequest`.

## Permissions

| Permission | Use |
|------------|-----|
| `wallet.top_up_requests.create` | Create request; update/resubmit own returned request. |
| `wallet.top_up_requests.view` | List own requests (`GET .../my`); view single if requester. |
| `wallet.top_up_requests.review` | List all tenant requests (`GET .../admin/...`); approve/reject/return; view any company request. |

## API (prefix `/api/v1`)

**Requester / company**

- `POST /wallet-top-up-requests` — multipart supported (`receipt` file). Bank transfer requires receipt.
- `GET /wallet-top-up-requests/my` — paginated own requests.
- `GET /wallet-top-up-requests/{id}` — detail (policy).
- `PATCH /wallet-top-up-requests/{id}` — only when `returned_for_revision`, own request.
- `POST /wallet-top-up-requests/{id}/resubmit` — back to `pending`.
- `GET /wallet-top-up-requests/{id}/receipt` — download (policy).

**Review**

- `GET /admin/wallet-top-up-requests` — filters: `status`, `customer_id`, `payment_method`, `from`, `to`.
- `POST /admin/wallet-top-up-requests/{id}/approve` — optional `note`.
- `POST /admin/wallet-top-up-requests/{id}/reject` — required `review_notes`.
- `POST /admin/wallet-top-up-requests/{id}/return` — required `review_notes`.

## Receipt storage

Receipts are stored on the **tenant upload disk** (not in DB, not in browser storage). Download is authorized by policy.

## Financial middleware note

Paths are registered as `api/v1/wallet-top-up-requests` so they are **not** treated as `api/v1/wallet/*` for idempotency-key rules (segment boundary check in `FinancialOperationProtectionMiddleware`).

## Frontend

Staff: **`/wallet/top-up-requests`** — tabs for “my requests” and “review” (when permitted), create form, detail, edit/resubmit, review actions with confirmation on approve.

## Tests

`tests/Feature/Wallet/WalletTopUpRequestWorkflowTest.php` covers create (no credit), approve + idempotent second approve, reject, return → resubmit → approve.
