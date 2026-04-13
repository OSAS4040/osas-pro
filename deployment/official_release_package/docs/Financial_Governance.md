# Financial governance (billing model & sensitive operations)

## Company billing model

- New companies default to `financial_model_status = pending_platform_review` at the DB level; tests and legacy behaviour use `approved_prepaid` via `TestCase::createCompany()` and the data migration backfill.
- Platform admins set model and status via `PATCH /api/v1/platform/companies/{id}/financial-model` (see `PlatformAdminController`).
- `BillingModelPolicyService` gates tenant operations: no money movement until status is `approved_prepaid` or `approved_credit`; wallet top-up only for approved prepaid; receivable/credit postings only for approved credit.

## Append-only receivables

- Table `company_receivables_ledger` (see migration `2026_04_10_100100_*`).
- `CompanyReceivableService` posts charges/reversals with idempotency keys; `CreditLimitService` enforces limit from net exposure.

## Work orders

- Status flow includes `pending_manager_approval` → `approved` → `in_progress` → …
- Credit tenants: on transition to `approved`, `WorkOrderApprovedCreditBridge` issues one invoice and one receivable line (idempotent per work order).
- Formal cancellation: `work_order_cancellation_requests` + internal `support_tickets`; approved cancellation reverses credit receivable where applicable, reverses **prepaid** `InvoiceDebit` wallet rows for the linked invoice, cancels the invoice row, sets work order `cancelled`.

## Sensitive preview tokens

- `POST /api/v1/sensitive-operations/preview` returns `sensitive_preview_token` (cache, 10 minutes).
- Required on:
  - `PATCH /api/v1/work-orders/{id}/status` when `status=approved`
  - `POST /api/v1/work-orders/batches`
  - `POST /api/v1/work-orders/{id}/cancellation-requests`
- Implementation: `SensitivePreviewTokenService` (`work_order_batch_create`, `work_order_status_to_approved`, `work_order_cancellation_request`).

## Frontend

- `SensitiveOperationReviewModal.vue` shows preview payload; `WorkOrderShowView.vue` uses it for approval and cancellation request; `WorkOrderBatchCreateView.vue` (`/work-orders/batch`) for batch create.
- **لوحة مشغّل المنصة** (`AdminDashboardView`): تبويب «النموذج المالي» يستدعي `PATCH /platform/companies/{id}/financial-model`؛ تبويب «إلغاء أوامر العمل» يستدعي `GET /platform/work-order-cancellation-requests` و`POST .../approve|reject`.

## Platform APIs (إضافية)

- `GET /api/v1/platform/work-order-cancellation-requests` — قائمة طلبات الإلغاء (مشغّل منصة فقط).
- `POST /api/v1/platform/work-order-cancellation-requests/{id}/approve` — body: `note` اختياري.
- `POST /api/v1/platform/work-order-cancellation-requests/{id}/reject` — body: `review_notes` إلزامي.

## بوابات فوترة إضافية

- **POS** (`POSController::sale`): `assertTenantMayOperate`؛ وعند الدفع بالمحفظة `assertPrepaidWalletTopUp`.
- **دفع فاتورة** (`InvoiceController::pay`): نفس المنطق للمحفظة.
- **فاتورة من أمر عمل** (`fromWorkOrder`): `assertTenantMayOperate`.
- **مشتريات** (`PurchaseController`): `assertTenantMayOperate` على إنشاء/استلام/حالة/مرفقات المستندات.
