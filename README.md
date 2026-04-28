# AutoService SaaS Platform

Production-grade multi-tenant SaaS for automotive service centers and fleet management.

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel (latest stable) |
| Database | PostgreSQL 15 |
| Cache / Queue / Locks | Redis 7 |
| Frontend | Vue 3 + TypeScript + Pinia + Vue Router |
| Web Server | Nginx |
| Containerization | Docker + Docker Compose |
| Error Tracking | Sentry |
| API Docs | Swagger / OpenAPI (l5-swagger) |

---

## Project Structure

```
project-root/
├── backend/                    # Laravel modular monolith
│   ├── app/
│   │   ├── Enums/              # Typed enums (WalletTransactionType, PurchaseStatus ...)
│   │   ├── Http/
│   │   │   ├── Controllers/Api/V1/   # All API controllers
│   │   │   ├── Middleware/           # Tenant, Subscription, Trace, ApiKey, Idempotency ...
│   │   │   └── Requests/             # Form requests
│   │   ├── Jobs/               # Queued jobs (webhooks, expiry, checks)
│   │   ├── Models/             # Eloquent models with HasTenantScope
│   │   ├── Policies/           # Authorization policies
│   │   ├── Services/           # Business logic layer
│   │   └── Traits/             # HasTenantScope, etc.
│   ├── bootstrap/app.php       # Middleware aliases, exception handler (Sentry)
│   ├── database/
│   │   ├── migrations/         # Schema history (many files; see directory)
│   │   ├── seeders/            # PlanSeeder, RolePermissionSeeder, DemoCompanySeeder
│   │   └── factories/          # Optional Eloquent factories (if present)
│   ├── routes/
│   │   ├── api.php             # All /api/v1/ routes
│   │   └── console.php         # Scheduled jobs
│   └── tests/
│       └── Feature/            # Feature tests by domain
├── frontend/                   # Vue 3 SPA
│   ├── src/
│   │   ├── api/                # HTTP client (axios)
│   │   ├── components/         # Shared components
│   │   ├── router/             # Vue Router configuration
│   │   ├── stores/             # Pinia stores
│   │   ├── utils/              # idempotency key generator
│   │   └── views/              # Feature-based pages
│   ├── env.example             # Frontend env template (copy to .env)
│   └── env.example.txt         # Deprecated pointer → use env.example
├── docker/                     # Nginx config, PHP Dockerfile
├── docker-compose.yml          # Services: app, nginx, postgres, redis, queue workers
├── Makefile                    # Developer convenience commands
└── README.md                   # This file
```

---

## Migrations

All schema changes live under `backend/database/migrations/`. The set evolves with the product; apply with:

```bash
cd backend && php artisan migrate
```

(Docker: run inside the `app` container.) Early migrations introduce core tables (companies, branches, users, subscriptions, wallets, inventory, work orders, invoices, etc.); later files add SaaS, intelligence, governance, and operational features.

---

## Quick Start (Docker)

### Prerequisites
- Docker Desktop
- Make (optional but recommended)

### 1. Clone and configure

```bash
git clone <repo>
cd project-root
```

Copy environment files:
```bash
cp backend/.env.example backend/.env
cp frontend/env.example frontend/.env
```

Edit `backend/.env`:
- Set `APP_KEY` via `php artisan key:generate` (step 3 or before first `docker compose up`)
- **Docker:** `DB_HOST=postgres`, `REDIS_HOST=redis` (match `docker-compose.yml`). **Local PHP:** `DB_HOST=127.0.0.1` as in `.env.example`.
- Set `SENTRY_LARAVEL_DSN` if using Sentry

### 2. Build and start

```bash
make build
docker compose up -d
```

Or without Make:
```bash
docker compose build --no-cache
docker compose up -d
```

### 3. Initialize backend

```bash
# Generate app key
make key

# Run all migrations
make migrate

# Seed initial data (plans, roles, demo company)
make seed
```

Or without Make:
```bash
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

### 4. Generate Swagger docs

```bash
make swagger
```

Access at: http://localhost/api/documentation

### 5. Access the app

| Service | URL |
|---|---|
| Frontend SPA | http://localhost |
| Backend API | http://localhost/api/v1 |
| Swagger UI | http://localhost/api/documentation |

### Demo credentials (after seeding)

| Role | Email | Password |
|---|---|---|
| Owner | `owner@demo.sa` | `Password123!` |
| Manager | `manager@demo.sa` | `Password123!` |
| Cashier | `cashier@demo.sa` | `Password123!` |
| Technician | `tech@demo.sa` | `Password123!` |

---

## Running Tests

```bash
# Run all tests in parallel
make test

# Run a specific test filter
make test-filter filter=WalletTest

# Run with coverage
make test-coverage

# Run directly inside container
docker compose exec app php artisan test
docker compose exec app php artisan test --filter=POSSaleTest
docker compose exec app php artisan test --filter=PurchaseOrderTest
docker compose exec app php artisan test --filter=WalletTest
docker compose exec app php artisan test --filter=PaymentServiceTest
docker compose exec app php artisan test --filter=ApiKeyAuthTest
docker compose exec app php artisan test --filter=WebhookSignatureTest
docker compose exec app php artisan test --filter=TraceIdTest
```

### Test coverage map

| Domain | Test File |
|---|---|
| POS / Invoicing | `tests/Feature/POS/POSSaleTest.php` |
| Idempotency | `tests/Feature/POS/IdempotencyTest.php` |
| Wallets | `tests/Feature/Wallet/WalletTest.php` |
| Payments | `tests/Feature/Wallet/PaymentServiceTest.php` |
| Purchase Orders + GRN | `tests/Feature/Purchases/PurchaseOrderTest.php` |
| API Key Auth + Webhooks + Trace | `tests/Feature/Integration/IntegrationTest.php` |

---

## Docker Services

| Service | Description | Port |
|---|---|---|
| `app` | Laravel (PHP-FPM) | internal |
| `nginx` | Web server | 80 |
| `postgres` | PostgreSQL 15 | 5432 (internal) |
| `redis` | Redis 7 | 6379 (internal) |
| `queue_high` | High-priority worker (financial) | — |
| `queue_default` | Default worker (webhooks, general) | — |
| `queue_low` | Low-priority worker (notifications) | — |
| `scheduler` | Artisan schedule:run (cron) | — |
| `frontend` | Vite dev server (dev only) | 5173 |

---

## Environment Variables Reference

### Backend (`backend/.env`)

```env
APP_NAME="AutoService SaaS"
APP_ENV=production
APP_KEY=base64:...          # php artisan key:generate
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=saas_db
DB_USERNAME=saas_user
DB_PASSWORD=<strong-password>

REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=<strong-password>

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis

SENTRY_LARAVEL_DSN=https://...@o0.ingest.sentry.io/...
SENTRY_TRACES_SAMPLE_RATE=0.05   # 5% in production

L5_SWAGGER_GENERATE_ALWAYS=false
L5_SWAGGER_CONST_HOST=https://your-domain.com/api/v1

AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=me-south-1
AWS_BUCKET=your-bucket

SUBSCRIPTION_GRACE_PERIOD_DAYS=15
IDEMPOTENCY_TTL_HOURS=24
```

### Frontend (`frontend/.env`)

```env
VITE_API_BASE_URL=https://your-domain.com/api/v1
VITE_SENTRY_DSN=https://...@o0.ingest.sentry.io/...
VITE_SENTRY_ENVIRONMENT=production
```

---

## API Structure

Base URL: `/api/v1/`

### Authentication
- `POST   /auth/login`
- `POST   /auth/logout`
- `GET    /auth/me`
- `POST   /auth/refresh`

### Core Resources
- `GET/POST         /companies`
- `GET/POST         /branches`
- `GET/POST/PUT/DELETE /users`
- `GET/POST         /roles`
- `GET/POST         /permissions`
- `GET/POST         /subscriptions`

### Customers & Vehicles
- `GET/POST/PUT/DELETE /customers`
- `GET/POST/PUT/DELETE /vehicles`

### Products & Inventory
- `GET/POST/PUT/DELETE /products`
- `GET/POST             /units`
- `GET                  /stock-movements`
- `GET/POST/PATCH       /reservations`

### Services & Work Orders
- `GET/POST/PUT/DELETE  /services`
- `GET/POST/PUT/DELETE  /bundles`
- `GET/POST/PUT/DELETE  /work-orders`
- `POST                 /work-orders/{id}/transition`

### Invoices & POS
- `GET                  /invoices`
- `POST (idempotent)    /invoices`
- `GET                  /invoices/{id}`
- `POST (idempotent)    /pos/sale`
- `POST                 /invoices/from-work-order/{workOrderId}`

### Wallets & Payments
- `GET                  /wallet?customer_id=`
- `GET                  /wallet/transactions?customer_id=`
- `POST (idempotent)    /wallet/top-up`
- `POST (idempotent)    /wallet/adjust`
- `POST                 /wallet/transactions/{id}/reverse`
- `POST (idempotent)    /payments`
- `POST (idempotent)    /payments/{id}/refund`
- `GET                  /payments/invoice/{invoiceId}`

### Suppliers & Purchases
- `GET/POST/PUT/DELETE  /suppliers`
- `GET/POST             /purchases`
- `GET                  /purchases/{id}`
- `PATCH                /purchases/{id}/status`
- `POST                 /purchases/{id}/receive`
- `GET/POST             /purchases/{id}/receipts`
- `GET                  /goods-receipts`
- `GET                  /goods-receipts/{id}`

### Integrations
- `GET/POST/DELETE      /api-keys`
- `GET/POST/DELETE      /webhooks`
- `GET                  /webhooks/{id}/deliveries`
- `GET                  /api-usage-logs`

---

## Frontend Pages

| Route | View | Description |
|---|---|---|
| `/login` | `LoginView` | Authentication |
| `/` | `DashboardView` | Overview dashboard |
| `/customers` | `CustomerListView` | Customer management |
| `/vehicles` | `VehicleListView` | Vehicle records |
| `/pos` | `POSView` | Point of sale |
| `/invoices` | `InvoiceListView` | Invoice list |
| `/invoices/:id` | `InvoiceDetailView` | Invoice detail |
| `/work-orders` | `WorkOrderListView` | Work order queue |
| `/work-orders/:id` | `WorkOrderDetailView` | WO detail |
| `/inventory` | `InventoryView` | Stock overview |
| `/products` | `ProductListView` | Product catalog |
| `/suppliers` | `SupplierListView` | Supplier management |
| `/purchases` | `PurchaseListView` | Purchase orders |
| `/purchases/new` | `PurchaseCreateView` | New PO |
| `/purchases/:id` | `PurchaseShowView` | PO detail + receipts |
| `/purchases/:id/receive` | `GoodsReceiptCreateView` | Receive goods |
| `/goods-receipts/:id` | `GoodsReceiptShowView` | GRN detail |
| `/wallet` | `WalletView` | Customer wallet |
| `/wallet/transactions` | `WalletTransactionsView` | Transaction history |
| `/payments` | `PaymentFlowView` | Record payment |
| `/reports` | `ReportsView` | Reports (scaffold) |
| `/settings` | `SettingsView` | System settings |
| `/settings/integrations` | `IntegrationsView` | API keys + webhooks |

---

## Architecture Notes

### Multi-Tenancy
- All business tables include `company_id`
- `HasTenantScope` trait applies global Eloquent scope
- `TenantScopeMiddleware` resolves tenant from authenticated user or API key
- `BranchScopeMiddleware` applies branch-level isolation where relevant

### Financial Immutability
- `wallet_transactions` and `stock_movements` are append-only
- Model `boot()` hooks throw `RuntimeException` on `update()` / `delete()`
- Corrections use reversal transactions with `original_transaction_id` / `reversal_transaction_id`

### Idempotency
- `idempotency_keys` table with `UNIQUE(company_id, key)`
- `IdempotencyMiddleware` checks key + payload hash before processing
- Same key + different payload → 422 rejection
- Frontend generates UUID-v4 per financial action

### Request Tracing
- Every request gets a `trace_id` (UUID) via `TraceRequestMiddleware`
- Propagated to: logs, jobs, webhook calls, Sentry context
- Returned in `X-Trace-Id` response header and all JSON error responses

### Queue Architecture
- `high_priority` — financial operations, critical jobs
- `default` — webhooks, general async tasks
- `low_priority` — notifications, reports, PDF generation
- Separate worker containers per queue in Docker

---

## Staging-first rollout (safe)

- **ترتيب التنفيذ الرسمي (مرقم):** [`docs/Execution_Order_Asas_Pro.md`](docs/Execution_Order_Asas_Pro.md) — ابدأ من المرحلة 0 ولا تتخطَّ مرحلة دون إكمال السابقة.
- **CI:** [`policy-env-on-pr.yml`](.github/workflows/policy-env-on-pr.yml) يفحص سياسة env على **كل** PR نحو `main`؛ [`staging-gate.yml`](.github/workflows/staging-gate.yml) للبوابة الكاملة حسب المسارات (Vitest + PHPUnit مراحل 0–7 + **`ocr:verify --fail`**).
- **Policy (Arabic, binding):** [`docs/Staging_Governance_Policy.md`](docs/Staging_Governance_Policy.md) — إغلاق حلقة Staging قبل التوسع.
- **Mandatory gate (PR / production):** [`docs/Staging_Gate_Mandatory_Policy.md`](docs/Staging_Gate_Mandatory_Policy.md) — `policy-env-example` + `staging-gate` (مع **Tesseract/OCR** في نهاية البوابة) قبل الدمج والإنتاج.
- **قالب PR:** [`.github/PULL_REQUEST_TEMPLATE.md`](.github/PULL_REQUEST_TEMPLATE.md) — قائمة تحقق عند فتح طلب الدمج.
- **حماية `main` + مراجعة:** [`docs/Branch_Protection_And_Review_Policy.md`](docs/Branch_Protection_And_Review_Policy.md) · إعداد GitHub: [`docs/GitHub_Branch_Protection_Setup.md`](docs/GitHub_Branch_Protection_Setup.md).
- **Runbook (Arabic):** [`docs/Staging_Deploy_Runbook.md`](docs/Staging_Deploy_Runbook.md) — staging قبل الإنتاج، متغيرات محافظة، فحص بعد النشر.
- **اختبار Staging يدوي (مرقم):** [`docs/Staging_Manual_Test_Checklist.md`](docs/Staging_Manual_Test_Checklist.md).
- **ما نفعله الآن + PASS/FAIL + ما لا نفعله:** [`docs/Staging_Execution_Now.md`](docs/Staging_Execution_Now.md).
- **Quick automated check (Docker):** `make staging-gate` — Vitest + PHPUnit مراحل 0–7 + **`make ocr-verify`** مدمج في السكربت؛ [`docs/Executive_Gate_Current_Phase.md`](docs/Executive_Gate_Current_Phase.md).
- **Example env:** [`backend/.env.staging.example`](backend/.env.staging.example), [`frontend/env.staging.example`](frontend/env.staging.example).

---

## Production Deployment (AWS)

### Recommended Services

| Component | AWS Service |
|---|---|
| Application | EC2 (t3.medium+) or ECS Fargate |
| Database | RDS PostgreSQL Multi-AZ |
| Cache / Queue | ElastiCache Redis (cluster mode disabled) |
| File Storage | S3 |
| CDN / SSL | CloudFront + ACM |
| Load Balancer | ALB |
| Secrets | AWS Secrets Manager |
| Logs | CloudWatch Logs |
| Container Registry | ECR |

### Production Checklist

```
[ ] Set APP_ENV=production, APP_DEBUG=false
[ ] Generate APP_KEY: php artisan key:generate
[ ] Set strong DB password via RDS + Secrets Manager
[ ] Set strong REDIS_PASSWORD via ElastiCache + Secrets Manager
[ ] Configure SENTRY_LARAVEL_DSN
[ ] Set FILESYSTEM_DISK=s3 + AWS credentials
[ ] Configure CORS_ALLOWED_ORIGINS to production domain only
[ ] Set L5_SWAGGER_GENERATE_ALWAYS=false
[ ] SaaS: `SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=false` — `SAAS_PLATFORM_ADMIN_EMAILS` لمشغّلي المنصة فقط (ليست قائمة طويلة تجريبية)
[ ] Run: php artisan config:cache
[ ] Run: php artisan route:cache
[ ] Run: php artisan view:cache
[ ] Run: php artisan migrate --force
[ ] Run: php artisan db:seed --class=PlanSeeder (first deploy only)
[ ] Run: php artisan db:seed --class=RolePermissionSeeder (first deploy only)
[ ] Set up queue workers (3 per queue) with supervisor / ECS task
[ ] Set up scheduler (single container running schedule:run every minute)
[ ] Configure ALB health check: GET /api/v1/health → 200
[ ] Enable RDS automated backups (7-day retention minimum)
[ ] Set up CloudWatch alarms for: CPU, DB connections, queue depth
[ ] Configure S3 bucket policy + CloudFront for assets
[ ] Enable AWS Shield Standard (default)
[ ] Set PHP upload_max_filesize, memory_limit in php.ini
```

### Nginx Production Config Notes
- Terminate SSL at ALB level (HTTP between ALB and Nginx)
- Set `X-Forwarded-For` + `X-Forwarded-Proto` headers at ALB
- Enable `HSTS` and `Content-Security-Policy` headers in Nginx

### Scaling Notes
- Queue workers scale independently per queue (high_priority most critical)
- Scheduler runs on a single dedicated instance (use ECS task with 1 replica)
- PostgreSQL read replicas for reports/analytics (future)
- Redis persistence: AOF enabled, `appendfsync everysec`

---

## Developer Commands (Makefile)

```bash
make up            # Start all services
make down          # Stop all services
make build         # Rebuild containers from scratch
make fresh         # Full reset: down + up + migrate:fresh --seed
make migrate       # Run pending migrations
make seed          # Run database seeders
make key           # Generate APP_KEY
make shell         # SSH into app container
make tinker        # Laravel Tinker REPL
make queue-restart # Restart all queue worker containers
make swagger       # Generate Swagger documentation
make test          # Run all tests in parallel
make staging-gate  # Vitest + PHPUnit phases 0–7 + ocr:verify --fail (Docker)
make ocr-verify      # Tesseract eng+ara only (app container; after compose up)
make preflight-pilot-readonly  # Read-only preflight (bash); see scripts/preflight-pilot-readonly.ps1 on Windows
make execution-order-local  # policy-env-example + printed hints for phases 1–5 (Node)
make execution-order-local-ps  # Windows + pwsh: same (no GNU Make); أو: powershell -File scripts/execution-order-local.ps1
make policy-env-example  # Static check: env *.example must not enable tenant catalog edit (Node)
make test-filter filter=TestName  # Run specific test
make test-coverage # Run tests with coverage report
make logs          # Follow app container logs
make logs-all      # Follow all container logs
make ps            # Show container status
make install       # composer install + npm install
```

---

## Remaining Backlog (Non-Blocking)

These items are deferred and do not block the current production-ready foundation:

| Item | Priority | Notes |
|---|---|---|
| Dashboard widgets (chart data APIs) | Medium | Scaffold exists; data aggregation needed |
| Reports module (PDF/Excel export) | Medium | Use low_priority queue |
| Notifications (email/SMS) | Medium | Framework ready; templates needed |
| ZATCA e-invoicing phase 2 (QR + XML) | High | Hash fields + counter ready in DB |
| Customer portal (self-service login) | Low | Separate guard needed |
| Technician mobile app integration | Low | API layer ready |
| Advanced analytics / BI | Low | Read replica recommended |
| Two-factor authentication (2FA) | Medium | Add to auth flow |
| Audit log viewer (UI) | Low | DB records exist |
| Role management UI | Medium | API complete; frontend form needed |

---

## License

Proprietary — All Rights Reserved.
