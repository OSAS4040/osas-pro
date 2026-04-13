# Platform Metrics Definition (Phase 2)

هذا المرجع يضبط تعريفات مؤشرات لوحة إدارة المنصة `(/admin)` لتفادي الأرقام المضللة.

## 1) Company State Definitions

- `active_company`:
  - شركة لديها نشاط تشغيلي خلال آخر 7 أيام.
  - النشاط المعتمد (V1): أمر عمل أو فاتورة أو حدث تسجيل دخول.
- `low_activity_company`:
  - شركة لديها نشاط خلال 8-14 يوم الماضية، ولا يوجد نشاط خلال آخر 7 أيام.
- `inactive_company`:
  - لا يوجد نشاط خلال آخر 14 يوم.
- `company_requiring_attention`:
  - أي شركة تحقق واحداً أو أكثر:
    - تجربة تنتهي خلال أقل من 3 أيام.
    - نشاط صفري خلال آخر 7 أيام.
    - حالة تشغيلية موقوفة أو `is_active = false`.
    - مراجعة نموذج مالي قيد الانتظار.

## 2) Activity Model (V1)

- `activity_score` (نسخة أولى بسيطة):
  - لكل شركة في نافذة 7 أيام:
    - `work_orders_count * 3`
    - `invoices_count * 2`
    - `logins_count * 1`
  - المعادلة:
    - `activity_score = 3*WO + 2*INV + 1*LOGIN`
- مصادر النشاط:
  - `work_orders.created_at`
  - `invoices.created_at`
  - `auth_login_events.created_at`

## 3) Revenue Definitions

- `catalog_mrr_estimate` (المعروض حالياً):
  - تقدير MRR من كتالوج الباقات، وليس إيراداً محاسبياً.
  - يحسب من `plans.price_monthly` لآخر اشتراك نشط لكل شركة.
- `actual_revenue`:
  - غير مفعل في Phase 2.
  - لاحقاً فقط من النظام المالي المحاسبي (خارج نطاق هذا المسار).

## 4) Alerts Severity

- `high`:
  - تجربة تنتهي خلال أقل من 3 أيام.
  - مهام فاشلة تتجاوز العتبة العالية.
- `medium`:
  - شركة منخفضة النشاط.
  - مراجعة مالية معلقة.
- `low`:
  - ملاحظات متابعة غير حرجة أو توصيات تحسين.

## 5) Health Definitions (Informational)

- `api`:
  - `ok` عندما استعلامات القراءة الأساسية تعمل.
- `queue`:
  - `ok` عندما عدد `failed_jobs` تحت العتبة.
- `trend`:
  - `stable` أو `degraded` بناءً على مقارنة `failed_jobs` بالعتبة.

## 6) Scope & Safety

- كل المؤشرات قراءة فقط.
- لا يسمح بأي `mutation` من مسار `/admin/overview`.
- لا تعديل على `ledger/journal/wallets` أو تدفقات المستأجر التشغيلية.
