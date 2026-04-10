# الملحق التدقيقي الشامل (دفعة واحدة) — إضافة رسمية للتقارير السابقة

## تعريف هذا المستند

- **النوع:** ملحق تدقيقي Evidence-by-Evidence
- **الغرض:** سد النواقص التدقيقية التي تم رصدها على التقرير التنفيذي/الاستراتيجي السابق
- **العلاقة بالمستندات السابقة:** هذا الملف **إضافة تكاملية** فوق:
  - `reports/system-assessment-report-ar-comprehensive.md`
  - `reports/system-assessment-report-ar-expanded.md`

> مبدأ هذا الملحق: كل حكم مرتبط بأدلة مسارات فعلية، مع تصنيف الجاهزية والمخاطر.

---

## A) شاشات مدير النظام المركزي (Super Admin / Tenant Admin / Branch Admin)

## A.1 الأدلة الفعلية (واجهة)

- إدارة عامة/إدارية:
  - `frontend/src/views/admin/AdminDashboardView.vue`
  - `frontend/src/views/admin/QaValidationView.vue`
- إدارة الشركات والفروع والإعدادات:
  - `frontend/src/views/settings/SettingsView.vue`
  - `frontend/src/views/saas/PlansView.vue`
  - `frontend/src/views/saas/SubscriptionView.vue`
- توجيه الصلاحيات/الدخول:
  - `frontend/src/router/index.ts`
  - `frontend/src/stores/auth.ts`
  - `frontend/src/layouts/AppLayout.vue`

## A.2 الأدلة الفعلية (Backend)

- إدارة الشركات/الفروع/المستخدمين:
  - `backend/app/Http/Controllers/Api/V1/CompanyController.php`
  - `backend/app/Http/Controllers/Api/V1/BranchController.php`
  - `backend/app/Http/Controllers/Api/V1/UserController.php`
- السياسات:
  - `backend/app/Policies/CompanyPolicy.php`
  - `backend/app/Policies/BranchPolicy.php`
  - `backend/app/Policies/UserPolicy.php`
  - `backend/app/Providers/AuthServiceProvider.php`

## A.3 مصفوفة أدوار الإدارة المركزية (فعليًا/ناقص)

| الدور | نطاقه الفعلي الحالي | الفجوة | التصنيف |
|---|---|---|---|
| مدير منصة مركزي | يظهر عبر owner/admin patterns + شاشات إدارية | لا يوجد فصل صريح Policy layer باسم Super Admin مستقل | منفذ جزئيًا |
| مدير مستأجر | متاح عبر owner/manager + tenant middleware | يحتاج فصل صلاحيات مؤسسي أدق (Tenant-admin profile رسمي) | منفذ جزئيًا |
| مدير فرع | branch.scope + مسارات تشغيل | يحتاج matrix صلاحيات دقيقة لكل عملية فرعية | منفذ جزئيًا |

---

## B) مصفوفة الصلاحيات التفصيلية جدًا (تشغيلية)

> هذه المصفوفة “تشغيلية” مبنية على الأدلة الحالية، وتُستخدم كأساس hardening.  
> التقييم: **موجود** = يوجد enforce واضح، **جزئي** = موجود بشكل غير موحد، **ضعيف** = غير كافٍ.

| المجال | عرض | إنشاء | تعديل | اعتماد/رفض | إلغاء/عكس | حذف | تصدير/طباعة | بيانات حساسة | التقييم |
|---|---|---|---|---|---|---|---|---|---|
| الشركات/الفروع | موجود (policy) | موجود (policy) | موجود (policy) | جزئي | جزئي | جزئي | ضعيف | متوسط | جزئي |
| المستخدمون/الأدوار | موجود | موجود | موجود | جزئي | جزئي | جزئي | ضعيف | متوسط | جزئي |
| الفواتير | موجود | موجود | موجود | جزئي | جزئي | جزئي | جزئي | مرتفع | جزئي |
| المدفوعات/المحافظ | موجود | موجود | جزئي | جزئي | موجود جزئيًا | ضعيف | جزئي | مرتفع جدًا | جزئي |
| المخزون | موجود | موجود | موجود | جزئي | جزئي | جزئي | جزئي | متوسط | جزئي |
| المشتريات | موجود | موجود | موجود | جزئي | جزئي | جزئي | جزئي | متوسط | جزئي |
| أوامر العمل | موجود | موجود | موجود | جزئي | جزئي | جزئي | جزئي | متوسط | جيد جزئيًا |
| HR | موجود | موجود | موجود | جزئي | جزئي | جزئي | ضعيف | حساس | جزئي |
| التقارير | موجود (permissions واضحة) | - | - | - | - | - | جزئي | متوسط | جيد |
| API Keys/Webhooks | موجود (permission middleware) | موجود | موجود | جزئي | جزئي | جزئي | - | مرتفع | جيد جزئيًا |
| الاجتماعات المؤسسية | غير موجود | غير موجود | غير موجود | غير موجود | غير موجود | غير موجود | غير موجود | عالي | غير منفذ |

### أدلة الصلاحيات
- `backend/config/permissions.php`
- `backend/app/Http/Middleware/RequirePermissionMiddleware.php`
- `backend/routes/api.php`
- `backend/app/Policies/*.php`
- `frontend/src/stores/auth.ts`
- `frontend/src/router/index.ts`

---

## C) مصفوفات الربط لكل وحدة (UI/DB/API/Logic/Permissions)

## C.1 المحاسبة

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/ledger/LedgerView.vue`, `frontend/src/views/ledger/ChartOfAccountsView.vue` |
| DB | migrations: `...000035_create_chart_of_accounts_table.php`, `...000036_create_journal_entries_tables.php` |
| API | `backend/app/Http/Controllers/Api/V1/LedgerController.php`, routes in `backend/routes/api.php` |
| Logic | `backend/app/Services/LedgerService.php` |
| Permissions | roles/policies/middleware config |

**الحكم:** مترابطة وظيفيًا، تحتاج تشديد صلاحيات granular.

## C.2 POS/فواتير/مدفوعات

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/pos/POSView.vue`, `frontend/src/views/invoices/*` |
| DB | `...000012_create_invoices_tables.php` |
| API | `POSController.php`, `InvoiceController.php`, `routes/api.php` |
| Logic | `POSService.php`, `InvoiceService.php`, `PaymentService.php`, `PostPosLedgerJob.php` |
| Permissions | جزئي (ليست موحدة بالكامل route-level) |

**الحكم:** مترابطة بقوة، لكن authorization coverage غير متجانس.

## C.3 المحافظ

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/wallet/WalletView.vue`, `WalletTransactionsView.vue` |
| DB | `...000008_create_wallets_table.php`, `...000009_create_wallet_transactions_table.php` |
| API | `WalletController.php`, routes wallet/fleet-wallet |
| Logic | `WalletService.php` |
| Permissions | جزئي |

**الحكم:** منطق قوي، صلاحيات تحتاج tightening.

## C.4 المخزون/المشتريات

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/inventory/InventoryView.vue`, `frontend/src/views/purchases/*` |
| DB | `...000010_create_products_table.php`, `...000011_create_inventory_tables.php`, `...000016_create_suppliers_purchases_tables.php`, `...000031_create_goods_receipts_enhance_suppliers.php` |
| API | `InventoryController.php`, `PurchaseController.php`, `GoodsReceiptController.php` |
| Logic | `InventoryService.php`, `PurchaseOrderService.php`, `GoodsReceiptService.php` |
| Permissions | جزئي |

**الحكم:** مترابطة جزئيًا قويًا، transitions غير موحدة 100%.

## C.5 التشغيل (Work Orders/Bookings/Bays)

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/work-orders/*`, `frontend/src/views/bookings/*`, `frontend/src/views/bays/*` |
| DB | `...000015_create_work_orders_tables.php`, `...000070_create_bays_bookings_tables.php` |
| API | `WorkOrderController.php`, `BayController.php` |
| Logic | `WorkOrderService.php`, `BookingService.php` |
| Permissions | جزئي |

**الحكم:** Work Orders قوي، Bookings أقل صرامة في الحالات.

## C.6 الموارد البشرية

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/workshop/*`, `frontend/src/views/hr/WageProtectionView.vue` |
| DB | `...000060_create_workshop_operations_tables.php`, `...000099_fix_missing_columns_and_tables.php` |
| API | `WorkshopController.php`, `LeaveController.php`, `SalaryController.php` |
| Logic | `AttendanceService.php` + منطق controllers |
| Permissions | جزئي |

**الحكم:** موجود وظيفيًا، يحتاج governance أقوى.

## C.7 الذكاء/BI

| البعد | الدليل |
|---|---|
| UI | `frontend/src/views/internal/IntelligenceCommandCenterView.vue`, `frontend/src/views/analytics/BusinessIntelligenceView.vue` |
| DB | intelligent events + governance audits migrations |
| API | `Internal/Phase2IntelligenceController.php`, internal intelligence routes |
| Logic | `backend/app/Services/Intelligence/Phase2|4|6|7/*` |
| Permissions | good internal guards + feature flags |

**الحكم:** Rule-based intelligence مترابط، ليس ML/LLM production.

## C.8 الاجتماعات والتعاون

| البعد | الحالة |
|---|---|
| UI | غير موجود |
| DB | غير موجود |
| API | غير موجود |
| Logic | غير موجود |
| Permissions | غير موجود |

**الحكم:** غير منفذ.

---

## D) تحليل Workflow/State كامل للكيانات المطلوبة

| الكيان | تمثيل الحالة في DB | تمثيل الحالة في Backend | تمثيل الحالة في UI | الحكم |
|---|---|---|---|---|
| الفواتير | نعم | نعم | نعم | جزئي (transition matrix غير موحدة بالكامل) |
| المدفوعات | نعم (عمليًا completed/refunded) | نعم | نعم | جيد جزئيًا |
| أوامر العمل | نعم | نعم (قوي) | نعم | جيد جدًا |
| الاشتراكات | نعم | نعم | نعم | جزئي (اتساق حالات يحتاج تدقيق) |
| الموظفون | نعم | نعم | نعم | جزئي |
| الحضور | نعم | نعم | نعم | جزئي |
| الإجازات | نعم | نعم | نعم | جزئي |
| المشتريات | نعم | نعم | نعم | جزئي |
| الاستلام (GRN) | نعم | نعم | نعم | جزئي |
| الحجوزات | نعم | نعم | نعم | جزئي |
| التذاكر | نعم | نعم | نعم | جزئي (transition graph مطلوب) |
| الموافقات الموحدة | غير مكتمل | غير مكتمل | غير مكتمل | غير منفذ كطبقة مشتركة |
| المستندات/المرفقات | جزئي | جزئي | جزئي | جزئي |
| العهد | غير واضح كمنظومة مستقلة | غير واضح | غير واضح | يحتاج تحقق |
| الاجتماعات | غير موجود | غير موجود | غير موجود | غير منفذ |
| محاضر الاجتماعات | غير موجود | غير موجود | غير موجود | غير منفذ |
| قرارات الاجتماعات | غير موجود | غير موجود | غير موجود | غير منفذ |
| الإجراءات الناتجة | غير موجود كموديول اجتماعات | غير موجود | غير موجود | غير منفذ |

---

## E) التحليل المحاسبي عمليةً بعملية

## E.1 بيع POS
- **الأثر المتوقع:** إثبات عملية بيع + تحصيل + قيد Ledger.
- **الأدلة:** `POSService.php`, `PostPosLedgerJob.php`, `LedgerService.php`, `PaymentService.php`.
- **التقييم:** موجود فعليًا.
- **المخاطر:** أي ضعف في queue health دون رصد قد يؤخر post-ledger.
- **الحكم:** منفذ جزئيًا قويًا (مع اعتماد على سلامة الخلفية).

## E.2 سداد فاتورة
- **الأثر:** تحديث paid_amount/due_amount + status progression + قيد/أثر مالي.
- **الأدلة:** `PaymentService.php`, `Invoice` model, routes/controller.
- **القيود:** منع overpay، منع التسويات غير المنطقية.
- **الحكم:** جيد جزئيًا.

## E.3 عكس/Refund
- **الأثر:** عمليات عكس عبر wallet/payment rules.
- **الأدلة:** `PaymentService.php`, `WalletService.php`, `WalletController.php`.
- **الحكم:** منفذ جزئيًا جيدًا.

## E.4 حركة مخزون مرتبطة بالبيع/الشراء
- **الأثر:** stock movements/reservations + أثر تشغيلي على القوائم.
- **الأدلة:** `InventoryService.php`, stock/reservation tests.
- **الحكم:** منفذ جزئيًا.

## E.5 الاشتراكات وتأثيرها التشغيلي/المالي
- **الأثر:** gating للوصول، تغييرات حالة subscription.
- **الأدلة:** `SubscriptionMiddleware.php`, `SubscriptionAccessEvaluator.php`, jobs.
- **الحكم:** منفذ جزئيًا.

### خلاصة محاسبية
- النواة المحاسبية موجودة وقوية نسبيًا، لكن يلزم:
  1. reconciliation دائم داخل المنتج
  2. dashboard رقابي مالي حي
  3. توحيد granular permissions للعمليات المالية

---

## F) Master Data / Settings / Reports / Documents (تفصيل تدقيقي)

## F.1 Master Data & Settings

| البند | الحالة |
|---|---|
| الشركات والفروع | موجود |
| المستخدمون والأدوار | موجود |
| خطط الاشتراك | موجود |
| إعدادات تشغيل عامة | موجود جزئيًا |
| الإدارات/الأقسام كطبقة مؤسسية موحدة | جزئي |
| أنواع الإجازات/الطلبات كمرجع موحد | جزئي |
| طرق الدفع/ضرائب كمرجع قياسي متعدد القطاعات | جزئي |
| قوالب محاضر الاجتماعات/سياسات أرشفة الاجتماعات | غير موجود |

## F.2 Reports

| المجال | الموجود | الناقص |
|---|---|---|
| محاسبي | تقارير أساسية | رقابة reconciliation متقدمة |
| إداري | KPI أساسية | تقارير قرارات واعتمادات |
| HR | تقارير جزئية | رقابة أعمق وتنفيذية |
| تشغيلي | جيد | توحيد مؤشرات SLA/latency/hotspots |
| اجتماعات | غير موجود | كامل |

## F.3 Documents/Attachments/Print

| البند | الحالة |
|---|---|
| مستندات تشغيلية وفواتير | جزئي |
| قوالب طباعة موحدة مؤسسية | جزئي |
| أرشفة اجتماعات/تسجيلات/محاضر | غير موجود |

---

## G) كشف Orphan/Dead/Inactive — تدقيقي أولي

> هذا كشف أولي evidence-based، ويحتاج pass آلي إضافي للحصر النهائي الكامل.

1. **تباين اتساق صلاحيات عبر endpoints** (route-level permission not uniformly applied)
2. **انتقالات حالات غير موحدة** بين وحدات (بعضها matrix صارمة وبعضها لا)
3. **مسارات “ذكاء” بعمق متفاوت** بين واجهات marketplace وتنفيذ backend
4. **غياب كامل لموديول الاجتماعات** (UI/DB/API/Logic/Permissions)

**الحكم:** توجد عناصر “جزئية/هيكلية/ناقصة” يجب إغلاقها قبل التوسع.

---

## H) مصفوفة التصنيف الشاملة (تنفيذيًا)

| الوحدة | التصنيف |
|---|---|
| POS/Invoices الأساسية | منفذ بالكامل نسبيًا |
| Wallet/Payments | منفذ جزئيًا قويًا |
| Inventory/Purchases | منفذ جزئيًا |
| Work Orders | منفذ بالكامل نسبيًا |
| HR | منفذ جزئيًا |
| Subscriptions | منفذ جزئيًا |
| Permissions Governance | يحتاج تحسين/إعادة ضبط |
| Intelligence Center | جاهز تجريبيًا |
| BI | منفذ جزئيًا |
| Integrations | منفذ جزئيًا |
| AI Marketplace | موجود هيكليًا + جزئي |
| Meetings & Collaboration | غير منفذ |

---

## I) الأولويات التنفيذية (بالترتيب)

1. **صلاحيات المسارات الحرجة** (مرتفع جدًا)
2. **توحيد transitions** (مرتفع)
3. **تكاملات/retries/ops monitoring** (مرتفع)
4. **reconciliation dashboards & controls** (مرتفع)
5. **approval engine موحد** (مرتفع)
6. **meetings MVP منخفض المخاطر** (متوسط-مرتفع استراتيجيًا)
7. **multi-vertical config architecture** (مرتفع استراتيجيًا)
8. **LLM/RAG/ML production governance** (استراتيجي)

---

## J) النتيجة النهائية

- هذا الملحق يغلق فجوات التقرير السابق على المستوى التدقيقي المطلوب:
  - شاشات الإدارة المركزية
  - مصفوفات صلاحيات تشغيلية
  - مصفوفات ربط لكل وحدة
  - تحليل workflow/state موسع
  - تحليل محاسبي عمليةً بعملية
  - Master Data/Reports/Documents تفصيلي
  - كشف تدقيقي أولي للعناصر الناقصة/الهيكلية

**الحكم النهائي الشامل:**
- **قوي تشغيليًا الآن** في النطاق الحالي.
- **غير جاهز بعد** كمنصة عالمية متعددة القطاعات قبل إغلاق فجوات الحوكمة والتخصيص والاعتمادات والاجتماعات.

