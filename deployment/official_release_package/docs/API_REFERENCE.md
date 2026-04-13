# API Reference

Base URL: `/api/v1`

Authentication: `Authorization: Bearer <token>`

All responses include `trace_id` field.

---

## Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /auth/register | Public | Register company + owner |
| POST | /auth/login | Public | Login, returns token |
| POST | /auth/logout | Bearer | Revoke current token |
| GET | /auth/me | Bearer | Get authenticated user |

---

## Companies & Branches

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /companies | Bearer | List companies |
| POST | /companies | Bearer | Create company |
| GET | /companies/{id} | Bearer | Get company |
| PUT | /companies/{id} | Bearer | Update company |
| DELETE | /companies/{id} | Bearer | Soft delete company |
| GET | /branches | Bearer | List branches |
| POST | /branches | Bearer | Create branch |
| GET | /branches/{id} | Bearer | Get branch |
| PUT | /branches/{id} | Bearer | Update branch |

---

## Subscriptions

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /subscriptions | Bearer | List subscriptions |
| GET | /subscriptions/current | Bearer | Get active subscription |
| POST | /subscriptions/renew | Bearer | Renew subscription |

---

## Customers

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /customers | Bearer | List (paginated, filterable) |
| POST | /customers | Bearer | Create customer |
| GET | /customers/{id} | Bearer | Get customer |
| PUT | /customers/{id} | Bearer | Update customer |
| DELETE | /customers/{id} | Bearer | Soft delete |

---

## Vehicles

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /vehicles | Bearer | List vehicles |
| POST | /vehicles | Bearer | Create vehicle |
| GET | /vehicles/{id} | Bearer | Get vehicle |
| PUT | /vehicles/{id} | Bearer | Update vehicle |
| DELETE | /vehicles/{id} | Bearer | Soft delete |

---

## Invoices (Idempotency required on POST)

| Method | Endpoint | Auth | Headers | Description |
|--------|----------|------|---------|-------------|
| GET | /invoices | Bearer | — | List invoices |
| POST | /invoices | Bearer | Idempotency-Key | Create invoice (atomic) |
| GET | /invoices/{id} | Bearer | — | Get invoice with items/payments |
| PUT | /invoices/{id} | Bearer | — | Update draft/pending invoice |
| DELETE | /invoices/{id} | Bearer | — | Delete draft invoice only |

### POST /invoices Body
```json
{
  "customer_id": 1,
  "customer_type": "b2c",
  "type": "sale",
  "discount_amount": 0,
  "currency": "SAR",
  "items": [
    {
      "product_id": 5,
      "name": "Engine Oil 5W-30",
      "quantity": 2,
      "unit_price": 45.00,
      "tax_rate": 15
    }
  ],
  "payment": {
    "method": "cash",
    "amount": 103.50
  }
}
```

---

## Wallet

| Method | Endpoint | Auth | Headers | Description |
|--------|----------|------|---------|-------------|
| GET | /wallet | Bearer | — | Get wallet balance |
| GET | /wallet/transactions | Bearer | — | List transactions |
| POST | /wallet/top-up | Bearer | Idempotency-Key | Top up wallet |

---

## Work Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /work-orders | Bearer | List work orders |
| POST | /work-orders | Bearer | Create work order |
| GET | /work-orders/{id} | Bearer | Get full work order |
| PATCH | /work-orders/{id}/status | Bearer | Transition status |
| DELETE | /work-orders/{id} | Bearer | Delete draft/cancelled only |

### Status Transitions
```
draft → pending
pending → in_progress | cancelled
in_progress → on_hold | completed | cancelled
on_hold → in_progress | cancelled
completed → delivered
delivered → (terminal)
cancelled → (terminal)
```

---

## Products

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /products | Bearer | List products |
| POST | /products | Bearer | Create product |
| GET | /products/{id} | Bearer | Get product |
| PUT | /products/{id} | Bearer | Update product |
| DELETE | /products/{id} | Bearer | Soft delete |

---

## Inventory

| Method | Endpoint | Auth | Headers | Description |
|--------|----------|------|---------|-------------|
| GET | /inventory | Bearer | — | List stock levels |
| GET | /inventory/{id} | Bearer | — | Get stock record |
| POST | /inventory/adjust | Bearer | Idempotency-Key | Adjust stock (add/subtract/set) |

### POST /inventory/adjust Body
```json
{
  "branch_id": 1,
  "product_id": 5,
  "quantity": 10,
  "type": "add",
  "note": "Stock received from supplier"
}
```

---

## Suppliers

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /suppliers | Bearer | List suppliers |
| POST | /suppliers | Bearer | Create supplier |
| GET | /suppliers/{id} | Bearer | Get supplier |
| PUT | /suppliers/{id} | Bearer | Update supplier |
| DELETE | /suppliers/{id} | Bearer | Soft delete |

---

## Purchases

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /purchases | Bearer | List purchase orders |
| POST | /purchases | Bearer | Create purchase order |
| GET | /purchases/{id} | Bearer | Get purchase order |
| POST | /purchases/{id}/receive | Bearer | Mark items received (adds stock) |

---

## API Keys (Internal Management)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /api-keys | Bearer | List API keys |
| POST | /api-keys | Bearer | Create API key |
| DELETE | /api-keys/{id} | Bearer | Revoke API key |

---

## Webhooks

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /webhooks | Bearer | List webhook endpoints |
| POST | /webhooks | Bearer | Register webhook endpoint |
| DELETE | /webhooks/{id} | Bearer | Remove webhook |

---

## Reports

| Method | Endpoint | Auth | Query Params | Description |
|--------|----------|------|--------------|-------------|
| GET | /reports/sales | Bearer | from, to, branch_id | Sales summary |
| GET | /reports/inventory | Bearer | low_stock, branch_id | Stock levels |
| GET | /reports/financial | Bearer | from, to | Payments by method |

---

## External API (API Key Auth)

Requires: `Authorization: Bearer <api_key>`

| Method | Endpoint | Headers | Description |
|--------|----------|---------|-------------|
| POST | /external/v1/invoices | Idempotency-Key | Create invoice via API key |
| GET | /external/v1/invoices/{uuid} | — | Get invoice by UUID |

---

## Error Responses

```json
{
  "message": "Human readable error",
  "trace_id": "uuid-v4",
  "errors": {
    "field": ["Validation message"]
  }
}
```

| Status | Meaning |
|--------|---------|
| 401 | Unauthenticated |
| 402 | Subscription required / suspended |
| 403 | Forbidden (tenant mismatch) |
| 422 | Validation error / business rule violation |
| 423 | Locked (grace period — read-only) |
| 429 | Rate limit exceeded |
| 500 | Server error (trace_id in response) |
