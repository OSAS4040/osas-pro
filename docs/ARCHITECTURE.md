# Architecture Overview

## System Type
Multi-tenant SaaS — Modular Monolith
- Multiple companies (tenants)
- Multiple branches per company
- Automotive service centers + fleet management
- B2C (retail POS) and B2B (prepaid fleet) flows

---

## Tech Stack

| Layer         | Technology                          |
|---------------|-------------------------------------|
| Backend       | Laravel 11 (PHP 8.3)                |
| Database      | PostgreSQL 16                       |
| Cache / Queue | Redis 7.2                           |
| Frontend      | Vue 3 + TypeScript + Pinia + Vite   |
| Web Server    | Nginx 1.25                          |
| Container     | Docker + Docker Compose             |
| Observability | Sentry + trace_id propagation       |
| API Docs      | Swagger / OpenAPI 3.0 (L5-Swagger)  |

---

## Folder Structure

```
new-project-2/
├── backend/                    # Laravel application
│   ├── app/
│   │   ├── Enums/              # PHP 8.1+ backed enums
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/   # Versioned API controllers
│   │   │   │   ├── Auth/             # Login, register, me, logout
│   │   │   │   └── External/         # API-key-authenticated endpoints
│   │   │   └── Middleware/           # Trace, Tenant, Subscription, Idempotency, ApiKeyAuth
│   │   ├── Jobs/               # Queued background jobs
│   │   ├── Models/             # Eloquent models + HasTenantScope trait
│   │   └── Services/           # Business logic (Invoice, Wallet, Inventory, WorkOrder)
│   ├── config/
│   │   └── sentry.php          # Sentry with trace_id + company_id tags
│   ├── database/
│   │   └── migrations/         # 20 ordered migrations
│   └── routes/
│       └── api.php             # All API routes under /api/v1/
├── frontend/                   # Vue 3 SPA
│   └── src/
│       ├── layouts/            # AppLayout shell
│       ├── lib/                # apiClient.ts (Axios + idempotency)
│       ├── router/             # Vue Router with auth guards
│       ├── stores/             # Pinia stores (auth, invoice, vehicle, workOrder)
│       └── views/              # Feature-based page components
├── docker/
│   ├── nginx/                  # nginx.conf + conf.d/default.conf
│   ├── php/                    # php.ini
│   └── postgres/               # init.sql (uuid-ossp, pg_trgm, btree_gin)
├── docs/                       # Architecture documentation
├── docker-compose.yml          # Full dev environment
└── Makefile                    # Developer shortcuts
```

---

## Multi-Tenancy Design

### Enforcement Layers
1. **Middleware** (`TenantScopeMiddleware`): Extracts `company_id` + `branch_id` from authenticated user, binds to app container.
2. **Global Scope** (`HasTenantScope` trait): Automatically filters all ORM queries by `company_id`. Applied to all operational models.
3. **Auto-fill on create**: `bootHasTenantScope()` automatically sets `company_id` and `branch_id` on new records.

### Tenant Isolation Rule
- Every operational/business table has `company_id` as first index segment.
- No cross-tenant query is possible without explicitly calling `withoutGlobalScope('tenant')`.

---

## Database Schema Summary

### Core Tables (20 migrations)

| Migration # | Tables Created |
|-------------|----------------|
| 000001 | companies |
| 000002 | branches |
| 000003 | users |
| 000004 | subscriptions |
| 000005 | idempotency_keys |
| 000006 | api_keys |
| 000007 | customers |
| 000008 | wallets |
| 000009 | wallet_transactions (append-only) |
| 000010 | products, product_categories |
| 000011 | inventory, inventory_reservations, stock_movements (append-only) |
| 000012 | invoices, invoice_items, payments (append-only) |
| 000013 | webhook_endpoints, webhook_deliveries |
| 000014 | vehicles |
| 000015 | work_orders, work_order_items, work_order_technicians |
| 000016 | suppliers, purchase_orders, purchase_order_items |
| 000017 | activity_logs, api_usage_logs, zatca_logs |
| 000018 | roles, permissions, model_has_roles, model_has_permissions, role_has_permissions |
| 000019 | personal_access_tokens (Sanctum) |
| 000020 | failed_jobs |

### Critical Schema Rules
- **UUID** on all primary business records
- **UNIQUE(company_id, invoice_number)**
- **UNIQUE(company_id, idempotency_key)**
- **UNIQUE(company_id, barcode)** on products
- **No UPDATE/DELETE** on: wallet_transactions, stock_movements, payments
- Corrections via **reversal transactions** only (original_transaction_id / reversal_transaction_id)

---

## Financial Safety

### Atomic Transaction Boundaries

**Retail Sale (POS / Invoice)**:
```
DB::transaction {
  1. lockForUpdate() on previous invoice (hash chain)
  2. Create Invoice
  3. Create InvoiceItems
  4. Create Payment
  5. Update Invoice paid_amount / due_amount / status
  6. WalletService::debit() if method=wallet
  7. InventoryService::deductStock() per item
}
```

**Wallet Top-Up**:
```
DB::transaction {
  1. lockForUpdate() on wallet row
  2. increment balance
  3. increment version (optimistic lock)
  4. Create WalletTransaction (append-only)
}
```

### Locking Strategy
| Scenario | Lock Type |
|----------|-----------|
| Wallet balance update | Pessimistic (lockForUpdate) |
| Inventory deduction | Pessimistic (lockForUpdate) |
| Invoice hash chain | Pessimistic (lockForUpdate) |
| Work order status | Optimistic (version column) |

---

## Subscription State Machine

```
active ──────────────────────────────────────────▶ grace_period ──▶ suspended
  │                                                      │
  │  (ends_at passed, within 15 days)                    │ (> 15 days)
  │                                                      │
  └──────────────────────────────────────────────────────┘
```

### Enforcement (SubscriptionMiddleware)
- `active` → full access
- `grace_period` → GET allowed, all write operations return HTTP 423
- `suspended` → all requests blocked (HTTP 402)

---

## Request Tracing

Every HTTP request gets a `trace_id` (UUID v4) via `TraceRequestMiddleware`.

Propagation path:
```
HTTP Request
  → TraceRequestMiddleware (generate/extract X-Trace-Id)
  → Bind to app container as 'trace_id'
  → All JSON responses include trace_id
  → All DB records store trace_id where relevant
  → Sentry events tagged with trace_id + company_id
  → Queue jobs receive trace_id in payload
```

Frontend sends:
- `X-Client-Request-Id` (generated per request)
- `Idempotency-Key` (for financial endpoints)

---

## Queue Architecture

| Queue | Workers | Purpose |
|-------|---------|---------|
| high_priority | Dedicated (saas_queue_high) | Financial transactions, critical ops |
| default | Dedicated (saas_queue_default) | Operational tasks |
| low_priority | Dedicated (saas_queue_low) | Reports, PDF, notifications |

Rules:
- Workers have independent Docker services
- Retry with exponential backoff (3 attempts max)
- Failed jobs visible in `failed_jobs` table
- External sync failures marked `sync_failed` and re-queued

---

## API Design

### Authentication
- **Internal**: Laravel Sanctum Bearer tokens (`Authorization: Bearer <token>`)
- **External / Integration**: HMAC API keys (`Authorization: Bearer <raw_key>` → SHA-256 hash matched against `api_keys.secret_hash`)

### Versioning
All routes prefixed: `/api/v1/`

### Idempotency
- Required header: `Idempotency-Key: <uuid>`
- Applied to: POST /invoices, POST /wallet/top-up, POST /inventory/adjust
- Same key + different payload → HTTP 422
- Same key + same payload → replay stored response

### Response Format
```json
{
  "data": { ... },
  "trace_id": "uuid-v4"
}
```

---

## Frontend Architecture

### State Management (Pinia)
- `useAuthStore` — user, token, login/logout/fetchMe
- `useInvoiceStore` — invoice list, creation flow
- `useVehicleStore` — vehicle CRUD
- `useWorkOrderStore` — work order state machine

### HTTP Client
`src/lib/apiClient.ts` (Axios):
- Auto-attach Bearer token from localStorage
- Auto-generate `X-Client-Request-Id` per request
- `withIdempotency()` helper for financial actions
- Auto-redirect to /login on 401

### Route Guards
- `requiresAuth: true` → redirect to /login if no token
- `guest: true` → redirect to / if already authenticated
- `requiresManager: true` → redirect to / if not owner/manager

---

## Docker Services

| Service | Container | Purpose |
|---------|-----------|---------|
| app | saas_app | Laravel PHP-FPM |
| nginx | saas_nginx | Reverse proxy (80/443) |
| frontend | saas_frontend | Vite dev server (5173) |
| postgres | saas_postgres | PostgreSQL 16 |
| redis | saas_redis | Cache + Queue + Sessions |
| queue_high | saas_queue_high | high_priority worker |
| queue_default | saas_queue_default | default worker |
| queue_low | saas_queue_low | low_priority worker |
| scheduler | saas_scheduler | Laravel cron scheduler |

---

## ZATCA Readiness

Invoices table includes:
- `uuid` — globally unique
- `invoice_number` — UNIQUE(company_id, invoice_number)
- `invoice_hash` — SHA-256 of (number + total + previous_hash)
- `previous_invoice_hash` — cryptographic chain
- `invoice_counter` — sequential per company
- `zatca_status` — sync status
- `zatca_logs` table — full request/response audit trail

---

## Getting Started

```bash
# 1. Copy env file
cp .env.example .env

# 2. Build and start all services
make build
make up

# 3. Generate application key
make key

# 4. Run migrations
make migrate

# 5. Generate Swagger docs
make swagger

# 6. Access
#   Frontend: http://localhost
#   API:      http://localhost/api/v1
#   Swagger:  http://localhost/api/documentation
```

---

## KPI Targets

| Metric | Target |
|--------|--------|
| POS full response | < 1.5 seconds |
| Invoice creation | < 800ms |
| Tenant data leakage | Zero tolerance |
| Load testing | Must pass |
