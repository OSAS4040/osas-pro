# خريطة التنقل (المستأجر) ↔ واجهات API — المرحلة 0

**الغرض:** جدول تركيز يربط مسارات الواجهة (`AppLayout.vue` + `router`) بمسارات HTTP تحت **`/api/v1`** بعد وسائط `auth:sanctum` + `tenant` + `financial.protection` + `branch.scope` (حيث ينطبق) + `subscription`.  
**المصدر البرمجي للقائمة الجانبية:** `frontend/src/layouts/AppLayout.vue` (أقسام `NavSection` / `NavItem`).  
**المصدر البرمجي لمسارات الواجهة (Staff / Fleet / Customer):** `frontend/src/router/index.ts` — يُستخرج منها تلقائياً قائمة المسارات أدناه.  
**المصدر البرمجي للـ API:** `backend/routes/api.php` (المجموعة من السطر ~336 تقريباً).

**تحقق ربط الراوتر ↔ هذا الملف:** من مجلد `frontend` نفّذ `npm run docs:nav-api-check` (يجب أن ينجح بعد أي إضافة/حذف `path` في أقسام البوابات الثلاث).

**ليست في النطاق:** مسارات **`/api/v1/platform/*`** (مشغّل منصة) — لها مجموعة وسائط `platform.admin` و`platform.permission:*`؛ الواجهة تعرضها تحت `/platform/*` عند `auth.isPlatform`.

---

## وسوم الحالة (عمود «حالة التطابق»)

| الوسم | المعنى |
|--------|---------|
| `OK` | مسار واجهة + مجموعة API واضحة؛ الصلاحيات مذكورة في `routes/api.php` أو عبر Policy |
| `PARTIAL` | جزء من الشاشة يعتمد على عدة مسارات أو placeholder في الواجهة |
| `UI_ONLY` | عنصر تنقل أو شاشة بدون مسار REST مخصّص واضح (إعدادات عمومية، تبويبات متعددة) |
| `FLAG` | يعتمد على `featureFlags` أو `intelligent.*` بالإضافة إلى الصلاحيات |

---

## 1) تشغيلي (Operations)

| مسار الواجهة | بوابة الواجهة (ملخص) | واجهات API رئيسية (`GET`/`POST` …) | حالة |
|--------------|----------------------|--------------------------------------|------|
| `/` | لوحة التحكم | `GET /dashboard/summary`؛ إعداد: `GET /onboarding/setup-status` | OK |
| `/pos` | نقطة البيع | `POST /pos/sale`؛ كائنات: `customers`، `services`، `products` حسب الشاشة | PARTIAL |
| `/work-orders` | أوامر العمل | `GET/POST /work-orders`؛ `POST /work-orders/line-pricing-preview`؛ `POST /sensitive-operations/preview` | OK |
| `/bays`، `/bookings` | مناطق العمل / الحجوزات | `GET/POST /bays`؛ `GET/POST /bookings`؛ `PATCH /bookings/{id}` | OK |
| `/meetings` | الاجتماعات | بادئة `GET/POST /meetings/*` (صلاحيات `meetings.*`) | OK |
| `/bays/heatmap` | الخريطة الحرارية | `GET /bays/heatmap` | OK |
| `/customers` | العملاء | `GET /customers`؛ `POST /customers`؛ `GET /customers/{id}/profile` | OK |
| `/crm/quotes`، `/crm/relations` | CRM | `GET/POST /quotes`…؛ علاقات عبر واجهات العملاء/CRM حسب الشاشة | PARTIAL |
| `/vehicles` | المركبات | `GET /vehicles`؛ `POST /vehicles`؛ `GET /vehicles/resolve-plate`؛ هوية: `POST /vehicle-identity/resolve` | OK |
| `/fleet/verify-plate` | أسطول — التحقق من اللوحة | `GET /fleet/verify-plate` (`fleet.plate.verify`) | OK |
| `/fleet/wallet` | أسطول — المحافظ | `GET /fleet/customers`؛ عمليات محفظة أسطول عبر مجموعة `fleet`/`wallet` | PARTIAL |

**403 شائع:** `permission:*` على المسار؛ تعطيل `operations` في ملف النشاط يخفي الرابط للموظف (المالك يراه للتهيئة).

---

## 2) الموارد البشرية (`/workshop/*`)

| مسار الواجهة | واجهات API (`/api/v1/workshop/...`) | حالة |
|--------------|--------------------------------------|------|
| `/workshop/employees` | `GET/POST /workshop/employees`؛ `PUT /workshop/employees/{id}` (جزء منها `users.update`) | OK |
| `/workshop/tasks` | `GET/POST /workshop/tasks`؛ `PATCH /workshop/tasks/{id}/status` | OK |
| `/workshop/attendance` | `GET/POST /workshop/attendance/*` | OK |
| `/workshop/leaves` | `GET/POST /governance/leaves` (مجموعة governance + `users.update`) | PARTIAL |
| `/workshop/salaries` | `GET/POST /governance/salaries/*` | PARTIAL |
| `/workshop/commissions`، `/workshop/commission-policies` | `GET /workshop/commissions`؛ `GET/POST /workshop/commission-rules` | OK |
| `/workshop/hr-comms` | `GET/POST /workshop/communications/*` | OK |

**ملاحظة:** مسارات الإجازات والرواتب في `api.php` تحت بادئة **`governance`** وليست كلها تحت `workshop` — التسمية في الواجهة «ورشة» لكن الـ API موحّد في الحوكمة/الإدارة.

---

## 3) المالية والمحاسبة

| مسار الواجهة | واجهات API رئيسية | حالة |
|--------------|-------------------|------|
| `/invoices` | `GET/POST /invoices`؛ `POST /invoices/from-work-order/{id}`؛ `GET /invoices/{id}/pdf` | OK |
| `/financial-reconciliation` | بادئة `GET/POST /financial-reconciliation/*` (`reports.financial.view`) | OK |
| `/wallet` | `GET /wallet`؛ `GET /wallet/transactions`؛ `POST /wallet/top-up` … | OK |
| `/wallet/top-up-requests` | `GET/POST /wallet-top-up-requests`؛ `GET /wallet-top-up-requests/{id}/transfer-instructions`؛ `GET /admin/wallet-top-up-requests` للمراجعة | OK |
| `/purchases`، `/suppliers` | `apiResource` مشتريات/موردين + استلام بضائع حسب الشاشة | PARTIAL |
| `/ledger` | بادئة `/ledger/*` (`reports.accounting.view`) | OK |
| `/chart-of-accounts` | `GET/POST/PUT/DELETE /chart-of-accounts` | OK |
| `/zatca` | `GET/POST /zatca/*` | OK |
| `/fixed-assets` | **لا يوجد** مسار REST مخصّص للأصول بعد؛ الواجهة placeholder + `fixed_assets` في `feature-profile` | PARTIAL |

**403 شائع:** `wallet` معطّل عبر `ConfigResolver` في المتحكم؛ تعليمات التحويل **422** عند غياب حسابات الخزينة (`WalletTreasuryAccountsResolver`).

---

## 4) المخزون

| مسار الواجهة | واجهات API | حالة |
|--------------|------------|------|
| `/products` | `GET/POST/PUT /products` | OK |
| `/inventory`، `/inventory/units`، `/inventory/reservations` | موارد المنتجات/المخزون حسب الشاشة (`products`، حركات، وحدات) | PARTIAL |
| `/suppliers` (من قسم المخزون أيضاً) | `GET /suppliers`؛ عقود: صلاحيات موردين | PARTIAL |

---

## 5) التحليلات وذكاء الأعمال

| مسار الواجهة | واجهات API / ملاحظة | حالة |
|--------------|---------------------|------|
| `/business-intelligence` | واجهات داخلية تحت `GET /internal/intelligence/*` عند تفعيل الأعلام؛ إضافة تقارير حسب البناء | FLAG |
| `/reports` | تجميع تقارير متعددة (`reports.*`) حسب التبويب في `ReportsView` | PARTIAL |
| `/governance` | `GET/POST /governance/policies`، `workflows`، `audit-logs`، `alert-rules` — مجموعة `users.update` | OK |
| `/internal/intelligence` | `GET /internal/intelligence/*` + command-center (أعلام `intelligent.*`) | FLAG |

---

## 6) إداري واشتراك وعام

| مسار الواجهة | واجهات API رئيسية | حالة |
|--------------|-------------------|------|
| `/branches`، `/branches/map` | `GET/POST/PATCH /branches` | OK |
| `/contracts` | `GET/POST /governance/contracts` (+ مرفقات) | OK |
| `/documents/company` | إعدادات/وسائط شركة (`companies/{id}/settings`، رفع ملفات حسب الشاشة) | PARTIAL |
| `/activity` | سجلات: غالباً `GET /governance/audit-logs` أو مسارات نشاط حسب التطبيق | PARTIAL |
| `/account/sessions` | `GET/DELETE /auth/sessions`؛ `POST /auth/sessions/revoke-others` | OK |
| `/settings`، `/settings/*` | `GET/PATCH /companies/{id}/settings`؛ `GET/PATCH /companies/{id}/feature-profile`؛ `GET /companies/{id}/profile` | UI_ONLY |
| `/settings/api-keys` | مفاتيح API حسب المتحكم المخصّص (صلاحية `api_keys.manage`) | PARTIAL |
| `/referrals` | `GET/POST /governance/referrals/*`؛ `GET /governance/loyalty/*` | OK |
| `/support` | `GET/POST /support/tickets/*`؛ `GET /support/stats` | OK |
| `/subscription`، `/plans`، `/plugins` | `GET /subscription`؛ `POST /subscription/change`؛ `GET /plugins/tenant`؛ إلخ | PARTIAL |

---

## 7) منصة (واجهة `/platform/*`)

| مسار الواجهة | واجهات API | حالة |
|--------------|------------|------|
| `/platform/overview` | `GET /platform/companies`؛ `GET /admin/overview`؛ ملخصات أخرى حسب التبويب | PARTIAL |
| `/admin/qa` | `POST /internal/run-tests`؛ `GET /internal/test-results` (`users.update` + tenant) | FLAG |

---

## مصادر 403 / 422 (مرجع سريع)

| المصدر | متى |
|--------|-----|
| `subscription` middleware | اشتراك غير فعّال أو تجاوز حدود الباقة حسب التطبيق |
| `permission:*` | الدور لا يملك المفتاح في `config/permissions.php` |
| `tenant` / `branch.scope` | تعارض شركة/فرع مع المستخدم |
| `Gate::authorize` / Policy | مثال: `WalletTopUpRequestPolicy::view` لطلب شحن ليس للمستخدم |
| `abort(403)` في المتحكم | مثل تعطيل المحفظة في `WalletTopUpRequestController::ensureWalletEnabled` |
| `422` تحقق أعمال | مثال: تعليمات تحويل بدون حسابات خزينة؛ تحويل بنكي بدون إيصال |

---

## صيانة الملف

- عند إضافة **`NavItem`** جديد: أضف صفاً هنا + راجع `routes/api.php`.  
- عند إضافة **`path` في `router/index.ts`** (بوابة staff أو fleet أو customer): حدّث كتل `nav-doc-route-anchors` / `nav-doc-route-anchors-fleet-portal` / `nav-doc-route-anchors-customer` في أسفل هذا الملف ثم شغّل `npm run docs:nav-api-check`.  
- عند إضافة **مسار API** جديد لشاشة قائمة: حدّث الشاشة ثم هذا الجدول.  
- للّقطة التحليلية العامة للمنتج راجع أيضاً: [`Platform_Module_Status_Matrix.md`](./Platform_Module_Status_Matrix.md).

---

## ملحق — مسارات الراوتر المربوطة (تدقيق آلي)

الكتل التالية يقرأها `frontend/scripts/check-tenant-nav-api-doc.mjs` ويقارنها باستخراج من `router/index.ts`. **سطر واحد = مسار كامل في الواجهة.**

### Staff (`AppLayout`, `portal: 'staff'`)

```nav-doc-route-anchors
/
/about/capabilities
/about/deployment
/about/taxonomy
/access-denied
/account/sessions
/activity
/bays
/bays/heatmap
/bookings
/branches
/branches/map
/bundles
/business-intelligence
/chart-of-accounts
/companies/:companyId
/compliance/labor-law
/contracts
/contracts/:contractId/catalog
/crm/quotes
/crm/relations
/customers
/customers/:customerId
/customers/:customerId/reports
/documents
/documents/company
/electronic-archive
/financial-reconciliation
/fixed-assets
/fleet/transactions/:walletId
/fleet/verify-plate
/fleet/wallet
/fuel
/goods-receipts/:id
/governance
/internal/intelligence
/inventory
/inventory/reservations
/inventory/units
/invoices
/invoices/:id
/invoices/:id/smart
/invoices/create
/ledger
/ledger/:id
/meetings
/operations/global-feed
/plans
/plugins
/pos
/products
/products/:id/edit
/products/new
/profile
/purchases
/purchases/:id
/purchases/:id/receive
/purchases/new
/referrals
/reports
/services
/settings
/settings/api-keys
/settings/integrations
/settings/org-units
/settings/team-users
/subscription
/suppliers
/support
/vehicles
/vehicles/:id
/vehicles/:id/card
/vehicles/:id/passport
/wallet
/wallet/top-up-requests
/wallet/transactions
/wallet/transactions/:walletId
/work-orders
/work-orders/:id
/work-orders/batch
/work-orders/new
/workshop/attendance
/workshop/commission-policies
/workshop/commissions
/workshop/employees
/workshop/hr-archive
/workshop/hr-comms
/workshop/hr-signatures
/workshop/leaves
/workshop/salaries
/workshop/tasks
/workshop/wage-protection
/zatca
```

### Fleet portal (`FleetLayout`)

```nav-doc-route-anchors-fleet-portal
/fleet-portal
/fleet-portal/new-order
/fleet-portal/orders
/fleet-portal/top-up
/fleet-portal/vehicles
```

### Customer portal (`CustomerLayout`)

```nav-doc-route-anchors-customer
/customer
/customer/bookings
/customer/dashboard
/customer/invoices
/customer/notifications
/customer/pricing
/customer/vehicles
/customer/wallet
```

---

## ملحق — سيناريوهات قبول مختصرة (مرحلة 0)

| # | السيناريو | معيار النجاح |
|---|-----------|--------------|
| 1 | تسجيل دخول موظف مستأجر | `GET /auth/me` 200؛ ظهور شريط جانبي بعد `feature-profile` |
| 2 | إنشاء عميل من القائمة | `POST /customers` 201؛ ظهور في القائمة |
| 3 | أمر عمل → فاتورة | `POST /work-orders` ثم `POST /invoices/from-work-order/{id}` أو مسار POS حسب التدفق |
| 4 | شحن محفظة — تعليمات تحويل | `GET /wallet-top-up-requests/{id}/transfer-instructions` PDF أو 422 بدون حسابات |
| 5 | ملف نشاط | `PATCH /companies/{id}/feature-profile`؛ انعكاس في الواجهة بعد الحفظ |
| 6 | صلاحية مرفوضة | 403 على API + عدم ظهور الرابط أو `access-denied` عند التعمّد |
| 7 | منصة — تذكرة دعم | مسارات `/api/v1/platform/support/*` منفصلة عن `tenant` |
| 8 | بوابة عميل | مسارات تحت `/customer/*` مع Sanctum مناسب |
| 9 | مطابقة مالية | بادئة `financial-reconciliation` بصلاحية `reports.financial.view` |
| 10 | سياسات (governance) | `GET /governance/policies` مع `users.update` أو وضع قراءة محدود حسب الشاشة |

*آخر تحديث: ربط الراوتر بالوثيقة + سيناريوهات قبول مرحلة 0.*
