# Platform Module Status Matrix

**WAVE 0 — لقطة تحليلية للمستودع** (لا تعكس نتيجة تشغيل اختبارات كاملة في هذه الجلسة).

**وسوم الحالة:** `FULL` كامل للنطاق الحالي للمنتج | `PARTIAL` جزئي | `PLANNED` مخطط فقط | `RISK` منطقة حساسة | `DUP` ازدواجية صيانة

---

## 1. المنصة والمستأجر والهوية

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات (ملاحظة) | UX |
|--------|--------|-----------|---------------|---------------------|-----|
| Multi-tenant (company scope) | FULL | RISK عند تجاوز النطاق | `HasTenantScope`, middleware `tenant` | Feature tests متفرقة | — |
| Company lifecycle | FULL | متوسط | فروع، اشتراك، COA تلقائي | PHPUnit | شاشات إدارة شركة |
| Branch scope | FULL | متوسط | users, policies | — | اختيار فرع في الواجهة |
| User + role + permission | FULL | RISK صلاحيات | `config/permissions.php` | يحتاج مصفوفة Wave 12 | — |
| Phone OTP registration | PARTIAL | RISK توحيد هوية | `PhoneOtp`, `RegistrationProfile` | Wave 1 | مسارات `/phone/*` |
| Classic email/password auth | FULL | منخفض | Sanctum | — | `/login` |
| Platform admin login | PARTIAL | DUP محتمل مع `/login` | Sanctum | — | `/platform/login` |

---

## 2. التشغيل التجاري (الورشة / POS)

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| Customers | FULL | متوسط | محافظ، فواتير، مركبات | — | قوائم + تفاصيل |
| Vehicles | FULL | منخفض | عملاء | — | جواز وبطاقة رقمية |
| Work orders | FULL | عالٍ تشغيلياً | مخزون، تسعير، إلغاء | governance pricing | تدفقات طويلة |
| POS / Invoices | FULL | **RISK مالي** | payments, ledger, ZATCA | reconciliation reports | POS محسّن فهرسة |
| Products / Inventory | FULL | متوسط | فروع، حركات مخزون | — | — |
| Purchases / GRN | FULL | متوسط | موردين | — | — |
| Suppliers + contracts | PARTIAL | متوسط | عقود موردين | — | — |

---

## 3. المالية والمحاسبة

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| WalletService (credit/debit/transfer/reverse) | FULL | **RISK** | DB transactions, idempotency | race command موجود | محدود حسب الدور |
| Wallet ↔ Invoice/Payment link | FULL | **RISK** | migrations phase3 | JSON reconciliation | — |
| LedgerService (balanced post) | FULL | **RISK** | COA | unbalanced reports تحت `reports/testing` | API للمحاسب |
| Journal immutability (DB) | FULL | **RISK** | PostgreSQL triggers | — | — |
| Chart of accounts | FULL | عالٍ عند تغيير الأكواد | company seed | seed migrations | — |
| Company receivable ledger | PARTIAL | عالٍ | invoices | — | تقارير |
| Financial reconciliation runs | PARTIAL | عالٍ | findings, history | ملفات JSON | لوحات داخلية |
| Financial periods / close | PLANNED | — | — | — | Wave 7 |
| Treasury / bank / AR aging enterprise | PLANNED | — | — | — | Wave 7 |
| Accrual / deferred revenue engine | PLANNED | — | ledger | — | Wave 7 |

---

## 4. الاشتراك والباقات والامتثال

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| Plans / Subscriptions | PARTIAL–FULL | متوسط | company | migrations محاذاة | بوابة اشتراك |
| ZATCA integration surface | PARTIAL | **RISK** امتثال | invoices | — | Wave 8 |
| Tax on invoice (VAT lines in GL) | PARTIAL | **RISK** | FinancialGlMapping | — | — |

---

## 5. CRM ودعم العملاء والحوكمة

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| Quotes CRM | PARTIAL | منخفض | customers | — | Wave 3 |
| Customer relations view | PARTIAL | منخفض | customers | — | Wave 3 |
| Support tickets + SLA tables | PARTIAL–FULL | متوسط | users, branches | — | Wave 4 لتوسعة case mgmt |
| Meetings + actions | PARTIAL | متوسط | approvals | — | Wave 5 |
| Approval workflows (expanded) | PARTIAL | عالٍ حوكمة | meetings | migrations | Wave 12 |
| Contract service items (governance) | PARTIAL | متوسط | contracts | صلاحيات مفصّلة | Wave 6 |

---

## 6. التكاملات والإشعارات والذكاء

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| API keys / Webhooks | PARTIAL | أمني | logging | — | Wave 10 |
| Notifications (DB + push devices) | PARTIAL | منخفض | users | migration | Wave 10 |
| Plugins registry | PARTIAL | متوسط | tenant plugins | — | — |
| Intelligence domain events | PARTIAL | **يجب read-only للتأثير المالي** | Wallet events | — | Wave 13 |
| Command center governance audit | PARTIAL | حوكمة | intelligence | — | Wave 12 |

---

## 7. الموارد البشرية والمنصة

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| Employees / attendance | PARTIAL | متوسط | branches | — | Wave 11 |
| Platform HR enterprise | PLANNED | — | — | — | Wave 11 |

---

## 8. تقارير وبحث وإنتاجية

| الوحدة | الحالة | المخاطر | الاعتماديات | الاختبارات | UX |
|--------|--------|-----------|---------------|-------------|-----|
| Reports hub | PARTIAL | متوسط | صلاحيات reports.* | — | Wave 13 |
| Business intelligence (staff-gated) | PARTIAL | ذكاء قراءة | feature gates | — | Wave 13 |
| Global search / command palette | PLANNED | — | — | — | Wave 14 |

---

## 9. ازدواجية / دين تقني (للمعالجة لاحقاً)

| العنصر | النوع | التوصية |
|--------|-------|----------|
| مسارات `wallet` vs `wallets` vs `fleet` wallet في `api.php` | DUP | توحيد واجهة REST خلف facade واحد مع إبقاء مسارات deprecated |
| `/login` و `/platform/login` | DUP تجربة | مواصفات Wave 1 — دخول موحّد مع resolver |

---

*يُحدَّث هذا الملف عند إكمال كل موجة تنفيذية.*
