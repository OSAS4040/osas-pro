# خطة التنفيذ الرسمية — نظام الاشتراكات (Subscriptions)

**الحالة:** مرجع إلزامي للفريق — غير قابل للاجتهاد الفردي أو تضارب القرارات.  
**آخر تحديث:** 2026-04-16 — إقفال المرحلة 1 + المرحلة 2 (Flow أساسي + اختبارات)

---

## 1) القرار التنفيذي النهائي (غير قابل للنقاش)

**الخيار المعتمد:** **(A) هجرة تدريجية Controlled Migration** — وليس إعادة بناء كامل.

| السبب |
|--------|
| نظام حي + بيانات + اختبارات + إنتاج |
| إعادة البناء = مخاطرة عالية |
| الهجرة التدريجية = أمان + استمرارية + تحكم |

**الهدف الاستراتيجي:** بناء النظام الجديد **فوق القديم** ثم تحويل الترافيك تدريجياً وإيقاف القديم لاحقاً.

---

## 2) المواصفة المعتمدة (ملخص إلزامي)

تم اعتماد هيكل Laravel + Vue + API Endpoints يغطي على الأقل:

- الباقات، الإضافات، طلبات الدفع، التحويل البنكي، المطابقة، الاعتماد، الفواتير، التفعيل، الترقية، التخفيض، التجديد، الانتهاء.
- فصل الطبقات: **Controller** (استقبال + صلاحية + استدعاء Action/Service + Resource فقط)، **Request** (تحقق)، **Service** (منطق الاشتراك/الفوترة/المطابقة)، **Action** (سيناريو واحد واضح)، **Job** (خلفية).
- قواعد صارمة: **ممنوع** منطق تفعيل أو مالي داخل Controller؛ **ممنوع** Invoice قبل Payment؛ **ممنوع** تفعيل Subscription قبل Payment؛ **ممنوع** اعتماد تحويل مرتين؛ **ممنوع** تعديل Invoice بعد الإصدار؛ **ممنوع** خصم Wallet بدون قيد وسجل حركة.

**الحالات الرسمية (Enums):**

- `SubscriptionStatus`: pending, active, expired, suspended, scheduled_for_downgrade  
- `PaymentOrderStatus`: pending_payment, pending_transfer, awaiting_review, matched, approved, rejected, expired, cancelled  
- `PaymentStatus`: paid, failed, refunded, partially_refunded  
- `InvoiceStatus`: paid, refunded, partially_refunded, void  

**واجهة Vue المعتمدة:** `frontend/src/modules/subscriptions/` (api / types / composables / components / pages) + مسارات العميل والمنصة كما في المواصفة الأصلية.

**الاختبارات:** Feature تحت `backend/tests/Feature/Subscriptions/` وUnit تحت `backend/tests/Unit/Subscriptions/` واختبارات الوحدة الأمامية تحت `frontend/src/modules/subscriptions/__tests__/`.

> التفاصيل الكاملة للمسارات والملفات (Controllers، Services، Migrations، إلخ) هي نفس **المواصفة الكاملة** المعتمدة في المراجعة؛ هذا الملف يثبت **القرار والمراحل والقيود** ولا يستبدل الحاجة لعدم خلط المسؤوليات بين الطبقات.

---

## 3) تحليل الوضع الحالي في المستودع (قبل الهجرة)

| المجال | الواقع |
|--------|--------|
| مسارات العميل | تحت `v1` للمستأجر: `subscriptions` (محدود: فهرس، حالي، تجديد) عبر `SubscriptionController` — **لا** يطابق بعد مواصفة `plans` / `payment-orders` / `invoices` / `wallet` ضمن نفس البادئة بالكامل. |
| SaaS | مسارات مثل `GET /api/v1/plans` و`GET|POST /api/v1/subscription/*` عبر `SaasController` — **مختلفة** عن المسارات الموحدة `GET /api/v1/subscriptions/...`. |
| نماذج | يوجد جزئياً: `Plan`, `Subscription`, `Invoice`, `InvoiceItem`, `Payment`, `Wallet`, `WalletTransaction`، و`PlanAddon` / `SubscriptionAddon`. **ناقص** لمسار الدفع الرسمي: `PaymentOrder`, التحويل البنكي، المطابقة، `SubscriptionChange`, `AuditLog`، إلخ كما في المواصفة. |
| طبقات | منطق كثير ما زال في Controllers (مثل `SaasController`) — **يجب** نقله تدريجياً إلى Services/Actions وفق هذه الخطة. |
| Vue | **لا** يوجد بعد `frontend/src/modules/subscriptions/` بالهيكل المعتمد. |

---

## 4) الاستراتيجية المعتمدة — المراحل

### المرحلة 0 — تثبيت المرجع (إلزامي)

- هذا الملف (`backend/docs/Subscription_System_Execution_Plan.md`) هو **المرجع** لمنع الاجتهاد الفردي وتضارب القرارات.
- أي انحراف عن المراحل يتطلب **قراراً مكتوباً** وتحديثاً لهذا المستند.

### المرحلة 1 — طبقة موازية (بدون كسر القديم)

**❗ لا تلمس `SaasController` في هذه المرحلة (تعديل مباشر للمنطق).**

1. إنشاء namespace جديد: **`app/Modules/SubscriptionsV2/`**  
2. إنشاء الهيكل الأولي:

```text
app/Modules/SubscriptionsV2/
├── Models/
├── Services/
├── Actions/
├── DTOs/
├── Enums/
└── Policies/
```

**القاعدة:** النظام الجديد **يعيش جنب القديم** — لا يستبدله الآن.

### المرحلة 2 — قاعدة البيانات (Non-breaking)

إنشاء **جداول جديدة فقط** (أمثلة إلزامية من الخطة):

- `payment_orders`
- `bank_transfer_submissions`
- `bank_transactions`
- `reconciliation_matches`
- `subscription_changes`
- `audit_logs`

**❗ مهم:** لا تعديل جداول حالية، لا حذف، **لا rename** في هذه المرحلة.

### المرحلة 3 — Service Layer (أهم مرحلة)

- استخراج المنطق من `SaasController` (لاحقاً) إلى:  
  `Modules/SubscriptionsV2/Services/`  
  أمثلة: `SubscriptionService`, `PaymentOrderService`, `InvoiceService`, `ReconciliationService`, …

**قاعدة صارمة:** ❌ أي منطق مالي داخل Controller = **خطأ تنفيذ**.

### المرحلة 4 — API موازية (Parallel Routes)

- **لا تكسر** المسارات الحالية.
- إضافة المسارات الجديدة تحت البادئة المعتمدة للعميل/المنصة (مثال تنظيمي):

```php
Route::prefix('v1')->group(function () {
    Route::prefix('subscriptions')->group(function () {
        // V2 / المصدر الرسمي الجديد — تُضاف تدريجياً
    });
    Route::prefix('admin')->group(function () {
        Route::prefix('subscriptions')->group(function () {
            // إدارة المنصة — تُضاف تدريجياً
        });
    });
});
```

- الإبقاء على القديم:
  - `/api/v1/plans`
  - `/api/v1/subscription/*`

**القرار:** القديم = **deprecated**؛ الجديد = **المصدر الرسمي** عند اكتمال التحويل.

### المرحلة 5 — Adapter Layer (جسر مؤقت)

داخل Controllers القديمة، استبدال المنطق الداخلي تدريجياً باستدعاءات Service موحدة، مثال اتجاهي:

```php
// بدلاً من المنطق الضخم داخل الـ Controller
app(\App\Modules\SubscriptionsV2\Services\SubscriptionService::class)->handle(...);
```

**الهدف:** تقليل التكرار وتوحيد المنطق دون كسر العقود الحالية فجأة.

### المرحلة 6 — فرض Payment First (أخطر نقطة)

أي كود يفعّل `Subscription` أو يصدر `Invoice` **قبل** `Payment` المسجل يجب **إيقافه/تصحيحه** فوراً.

داخل Service (مثال اتجاهي للمبدأ):

```php
if (!$payment->isPaid()) {
    throw new \RuntimeException('Payment required');
}
```

### المرحلة 7 — Invoice Refactor

- **المطلوب:** الفاتورة تُنشأ **فقط** بعد Payment.
- مراجعة أي مسار ينشئ فاتورة مبكراً وتصحيحه ليطابق القاعدة.

### المرحلة 8 — Reconciliation Engine

- `ReconciliationService`، Scoring، Review Queue.
- **بدون** إزالة مسار الإيصال/التحويل الحالي قبل اكتمال الجسر.

### المرحلة 9 — Vue Module (بدون كسر القديم)

- إنشاء: `frontend/src/modules/subscriptions/`
- **لا** تعديل الشاشات القديمة في البداية.
- استخدام **Feature Flag** + **Route switching** للتحويل التدريجي.

### المرحلة 10 — Migration Switch

بعد اكتمال V2:

1. تحويل تدريجي للترافيك: 10% → 50% → 100%  
2. تعطيل القديم: `SaasController` → **Deprecated** رسمياً.

### المرحلة 11 — حماية الإنتاج (إلزامي)

- Feature Flags  
- Logging مكثف  
- مراقبة  
- **Rollback** جاهز

### المرحلة 12 — الاختبارات (Critical Gate)

قبل أي تحويل إنتاجي للترافيك:

- Payment flow  
- Bank transfer  
- Approval  
- Invoice after payment  
- Upgrade  
- Addons  
- Renewal  

---

## 5) قرارات حساسة (ملزمة)

### Addon vs PlanAddon

| القرار |
|--------|
| ✔ الاستمرار بـ **`PlanAddon`** حالياً |
| ✔ عمل **Adapter** نحو مفهوم `Addon` في الطبقة الجديدة عند الحاجة |

### naming mismatch

- **لا تغيير** أسماء الجداول/النماذج الحالية الآن.
- لاحقاً: **alias** ثم **deprecate** بترتيب خاضع للمراجعة.

---

## 6) شرط الإغلاق النهائي

لا يُعلن نجاح المشروع إلا عند تحقق **كل** ما يلي:

- ✔ كل الاشتراكات **الجديدة** تستخدم V2  
- ✔ لا يوجد **Invoice** بدون **Payment**  
- ✔ لا يوجد **Subscription** نشط/معتمد بدون **Payment** وفق المسار الرسمي  
- ✔ التحويل البنكي **end-to-end**  
- ✔ لا تضارب مالي  
- ✔ الاختبارات الحرجة **PASS** (هدف 100% للبوابة الحرجة)  
- ✔ الأداء مستقر  

---

## 7) الخلاصة التنفيذية للفريق

سيتم بناء نظام الاشتراكات **(V2)** بشكل **موازٍ** داخل `app/Modules/SubscriptionsV2/` **دون** تعديل مباشر على النظام الحالي في المراحل الأولى، مع نقل **تدريجي** للمنطق إلى **Services** و**Actions**، واعتماد **Payment First** و**Invoice After Payment** بشكل صارم، وإطلاق API جديدة تحت المسارات المعتمدة للاشتراكات مع إبقاء المسارات القديمة **deprecated** حتى اكتمال التحويل، ومنع أي تفعيل أو فوترة خارج مسار الدفع الرسمي، وتنفيذ التحويل على مراحل مع **Feature Flags** و**مراقبة** و**Rollback** جاهز.

---

## 8) سجل إتمام المرحلة 1 — Phase 1 Completion Record

**الحالة:** `PASS` (تم التنفيذ والتحقق من الـ migrations عبر Docker: `docker compose run --rm app php artisan migrate`).

### المجلدات التي أُنشئت

- `backend/app/Modules/SubscriptionsV2/Models/`
- `backend/app/Modules/SubscriptionsV2/Enums/`
- `backend/app/Modules/SubscriptionsV2/DTOs/` (`.gitkeep`)
- `backend/app/Modules/SubscriptionsV2/Services/` (`.gitkeep`)
- `backend/app/Modules/SubscriptionsV2/Actions/` (`.gitkeep`)
- `backend/app/Modules/SubscriptionsV2/Policies/` (`.gitkeep`)
- `backend/app/Modules/SubscriptionsV2/Support/` (`.gitkeep`)

### الـ Enums

- `PaymentOrderStatus`
- `ReconciliationMatchType`
- `SubscriptionLifecycleStatus`
- `BankTransferReviewStatus`

### الـ migrations (جداول جديدة فقط)

| الملف | الجدول |
|--------|--------|
| `2026_04_16_181000_create_subscriptions_v2_payment_orders_table.php` | `payment_orders` |
| `2026_04_16_181010_create_subscriptions_v2_bank_transactions_table.php` | `bank_transactions` |
| `2026_04_16_181020_create_subscriptions_v2_bank_transfer_submissions_table.php` | `bank_transfer_submissions` |
| `2026_04_16_181030_create_subscriptions_v2_reconciliation_matches_table.php` | `reconciliation_matches` |
| `2026_04_16_181040_create_subscriptions_v2_subscription_changes_table.php` | `subscription_changes` |
| `2026_04_16_181050_create_subscriptions_v2_audit_logs_table.php` | `subscriptions_v2_audit_logs` |

### الـ Models (V2)

- `PaymentOrder`
- `BankTransferSubmission`
- `BankTransaction`
- `ReconciliationMatch`
- `SubscriptionChange`
- `AuditLog` (`$table = 'subscriptions_v2_audit_logs'`)

### تحديث autoload

- **`composer.json`:** لم يُعدَّل — قاعدة PSR-4 الحالية `"App\\": "app/"` تغطي `app/Modules/SubscriptionsV2/` تلقائياً.
- **`composer dump-autoload -o`:** نُفِّذ داخل الحاوية `app` — **نجح** (exit 0). ظهرت تحذيرات PSR-4 قديمة لملفات `_container` في المشروع؛ ليست من V2.

### نتيجة `php artisan migrate`

- نُفِّذت عبر: `docker compose run --rm app php artisan migrate --force --no-interaction`.
- **النتيجة:** نجاح تنفيذ الست migrations أعلاه على PostgreSQL (لم يُلمَس جدول `subscriptions` أو `plans` أو غيرهما بتعديل/حذف).

### انحرافات اضطرارية عن الخطة النصية

1. **جدول التدقيق:** يوجد في المشروع مسبقاً جدول **`audit_logs`** (حوكمة/موافقات — migration `2024_01_01_000050_create_governance_tables.php`). لتجنب التعارض، جدول V2 اسمه **`subscriptions_v2_audit_logs`** والنموذج `AuditLog` يحدد `$table` صراحةً.
2. **عمود `transfer_time` / `transaction_time`:** أُبقي كـ `time` في قاعدة البيانات دون cast إلى `datetime` في Eloquent لتفادي سلوك خاطئ.

### ما لم يُنفَّذ في المرحلة 1 (حسب الأمر)

- لا تعديل على `SaasController`
- لا مسارات API جديدة
- لا Services منطق أعمال
- لا Vue
- لا إعادة تسمية `PlanAddon` أو الجداول القديمة

### الخطوة التالية (المرحلة 2 — خارج نطاق هذه الوثيقة حتى إقرارها)

Services + Actions الأساسية + أول flow رسمي (مع الالتزام بـ Payment First).

---

## 9) سجل إتمام المرحلة 2 — Phase 2 Completion Record

**الحالة:** `PASS` (اختبارات `tests/Feature/SubscriptionsV2/PaymentOrderFlowTest.php` — 7/7).

### Services (داخل `app/Modules/SubscriptionsV2/Services/`)

- `AuditLogService`
- `PaymentOrderService`
- `PaymentService`
- `InvoiceService`
- `SubscriptionService`
- `BankTransferService`
- `ReconciliationService` (placeholder — `markAsMatched` فارغ حالياً)

### Support

- `ResolveCompanyBillingBranch`

### Actions

- `CreatePaymentOrderAction`
- `SubmitBankTransferAction`
- `ApproveBankTransferAction` (مع `lockForUpdate` + `DB::transaction`)
- `RejectBankTransferAction`

### HTTP (رفيع)

- `App\Http\Controllers\Api\V1\SubscriptionsV2\TenantPaymentOrderController`
- `App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformPaymentOrderController`
- `App\Http\Requests\SubscriptionsV2\StorePaymentOrderRequest`
- `App\Http\Requests\SubscriptionsV2\SubmitBankTransferRequest`
- `App\Http\Requests\SubscriptionsV2\RejectPaymentOrderRequest`

### Endpoints (تحت `/api/v1/…`)

| الطريقة | المسار |
|--------|--------|
| `POST` | `/api/v1/subscriptions/payment-orders` |
| `POST` | `/api/v1/subscriptions/payment-orders/{id}/submit-transfer` |
| `POST` | `/api/v1/admin/subscriptions/payment-orders/{id}/approve` |
| `POST` | `/api/v1/admin/subscriptions/payment-orders/{id}/reject` |

**وسيطات:** مستأجر — `auth:sanctum` + `tenant` + `financial.protection` + `branch.scope` + `subscription` + `permission:subscriptions.manage`. منصة — `auth:sanctum` + `platform.admin` + `platform.permission:platform.subscription.manage`.

### Flow المعتمد

`Create PaymentOrder` → `Submit Bank Transfer` → `Admin Approve` → **`Payment` (بدون `invoice_id` أولاً)** → **`Invoice` + ربط الدفع** → **`Subscription` مُحدَّث** → تحديث `PaymentOrder` إلى `approved`.

### تدقيق (Audit) — تغطية

- `create_order`، `submit_transfer`، `create_payment`، `create_invoice`، `activate_subscription`، `approve_transfer`، `reject_transfer`  
- السجلات في جدول **`subscriptions_v2_audit_logs`**.

### انحرافات / قرارات تقنية (Phase 2)

1. **`payments`:** إضافة `payment_order_id` (FK → `payment_orders`) و**جعل `invoice_id` nullable** لتمكين مسار **Payment أولاً ثم ربط الفاتورة** دون كسر immutability إلا عبر تحديث **واحد** لـ `invoice_id` عندما يكون الأصل `null` (تعديل محدود في `App\Models\Payment::boot`).
2. **انتهاء الطلب عند `submit-transfer`:** رفض الطلب المنتهي بـ 422 **دون** تغيير الحالة إلى `expired` داخل نفس الـ transaction (لأن الـ rollback كان يلغي التحديث). يبقى `PaymentOrderService::expireOrder()` متاحاً لمهام مجدولة لاحقاً.
3. **الفاتورة:** تُنشأ بحالة `paid` مباشرة بعد `Payment` مكتمل ومرتبط بالطلب — لا صف فاتورة قبل إنشاء سجل الدفع.

### الاختبارات

- المسار: `backend/tests/Feature/SubscriptionsV2/PaymentOrderFlowTest.php`
- التحقق: إنشاء طلب، تحويل، موافقة كاملة، رفض، منع موافقة مرتين، رفض إرسال عند انتهاء الصلاحية، رفض `InvoiceService` بدون ربط دفع صالح.
