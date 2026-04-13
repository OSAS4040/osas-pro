# Platform Master Execution Blueprint

**الإصدار:** 0.2 (بعد إغلاق **WAVE 1** — PR6)  
**التاريخ المرجعي:** 2026-04-12  
**النطاق:** هذا المستند يحدّد الواقع الحالي للمستودع، المخاطر، المناطق المغلقة، والخطة الموجّهة بالموجات. **WAVE 0** كان توثيقياً فقط؛ **WAVE 1** نُفِّذ في الكود والاختبارات (PR1–PR5) وأُغلق رسمياً بـ **PR6** (راجع [`Platform_Wave1_Final_Closeout.md`](./Platform_Wave1_Final_Closeout.md)).

---

## 1. ملخص تنفيذي

المشروع منصة **SaaS متعددة المستأجرين** (شركة ← فروع ← مستخدمون) مع عميل نهائي نموذجي **ورشة/أسطول** (`Customer` + مركبات + أوامر عمل + فواتير). توجد طبقة **مالية ومحاسبية حقيقية**: محافظ عملاء (`Wallet` / `CustomerWallet` / `WalletTransaction`)، **قيود يومية مزدوجة** (`LedgerService` + `journal_entries` مع تريغرات منع التعديل/الحذف)، **دفتر أستاذ مساعد للذمم** (`CompanyReceivableLedger`)، تسويات مالية (`financial_reconciliation_*`)، وربط **فاتورة/دفع** بحركات المحفظة.

**مبدأ التنفيذ المعتمد:** Additive safe architecture — أي توسعة مؤسسية تمر عبر طبقات جديدة أو واجهات محددة، دون كسر `WalletService` / `LedgerService` / خرائط GL / تدفقات POS والفوترة دون اختبار وموافقة صريحة.

---

## 2. خريطة المستودع (ما هو موجود فعلياً)

| الطبقة | المسار / المؤشر | ملاحظة |
|--------|------------------|--------|
| Backend API | `backend/` Laravel 11، `routes/api.php` prefix `v1` | Sanctum، middleware: `tenant`, `financial.protection`, `branch.scope`, `subscription`, صلاحيات `permission:*` |
| Frontend | `frontend/` Vue 3 + Vite + TypeScript | بوابات: staff، fleet، customer، admin/platform؛ مسارات `/login` و `/platform/login` ومسار تسجيل هاتف `/phone/*` |
| قاعدة البيانات | PostgreSQL (ضمن Docker/تشغيل محلي حسب المشروع) | 100+ migration؛ فهارس أداء POS؛ جداول دعم، اجتماعات، plugins، ذكاء قراءة |
| الوثائق التشغيلية القائمة | `docs/*.md`، `scripts/staging-gate.sh`، GitHub workflows | يوجد مسار Staging موثّق مسبقاً |

---

## 3. المصفوفة الرئيسية (Module × Status × Risk)

> تغطية الاختبارات هنا **تقديرية من الواقع الملموس** (وجود مجلدات tests، أوامر gate، تقارير reconciliation تحت `backend/reports/`). يُحدَّث العمود بعد تشغيل CI محلي كامل.

| Module | Status | Risk | Dependencies | Test coverage (ملاحظة) | UX notes |
|--------|--------|------|--------------|---------------------------|----------|
| **Tenant & Company** | موجود قوي | متوسط — توسعة نماذج مالية شركة | `companies`, فروع، اشتراك | PHPUnit + سيناريوهات تشغيل | إعدادات شركة/نموذج مالي يحتاج وضوح للمشغّل |
| **Branches** | موجود | منخفض–متوسط | شركات، مستخدمون، نطاق فرع | فهارس وفحوصات نطاق | — |
| **Users & RBAC** | موجود | متوسط — تداخل أدوار fleet/staff | `permissions.php`, Spatie-like HasRoles | اختبارات صلاحيات جزئية | مساران دخول في الواجهة (`login` vs `platform-login`) |
| **Identity — هاتف / OTP** | **مغلق لـ WAVE 1** | متوسط مستقبلاً — توحيد الحساب العالمي بالجوال أوسع من النطاق | `PhoneOtp`, migrations 2026_04_12_*، مسارات `/phone/*`، `auth_security.php`، `auth_suspicious_login_signals` | **Regression:** `tests/Feature/Auth/*` + `tests/Unit/Auth/*` + Vitest `postLoginRedirect` / `loginApiErrors` | تدفق onboarding منفصل عن الدخول الكلاسيكي؛ عقد `account_context` + توجيه ما بعد الدخول موثّق في PR4 |
| **Customers (أفراد/أساطيل)** | موجود | متوسط — مصطلح "Customer" vs "Individual" في الطلب المؤسسي | محافظ، مركبات، فواتير | CRUD + مسارات API | قوائم وعروض موجودة؛ "ملف فرد مؤسسي" أوسع من النموذج الحالي |
| **CRM (Quotes, Relations)** | **جزئي** | منخفض | عملاء، صلاحيات | Vitest/Playwright حسب المسارات | مسارات `crm.quotes`, `crm.relations` |
| **POS / Invoices / Payments** | موجود قوي | **عالٍ مالياً** | محفظة، GL، ZATCA | تكاملات وتقارير reconciliation | مسار hot-path مفهرس |
| **Wallets & Top-up requests** | موجود قوي | **عالٍ** | Idempotency، `WalletService`، أحداث ذكاء قراءة | أوامر اختبار سباق (مثلاً `CtovWalletRaceAttemptCommand`) | واجهات طلب شحن/اعتماد |
| **Ledger / Journal / COA** | موجود | **عالٍ** — تريغرات DB، انعكاسات | `LedgerService`, `ChartOfAccount`, seed نظامي | تقارير JSON تحت `backend/reports/testing/` | شاشات تقارير محاسبية للأدوار المناسبة |
| **Wallet ↔ GL bridge** | موجود (`FinancialGlMapping`, `WalletGlMapping`) | **عالٍ** | تغيير أكواد الحسابات | يجب عدم كسر التوازن | — |
| **Subscriptions & Plans** | موجود | متوسط | شركات، حدود ميزات | migrations محاذاة أعمدة | بوابة الاشتراك في الواجهة |
| **Support / Ticketing** | موجود (جداول غنية) | متوسط — توسعة SLA/قنوات | `support_tickets`, `sla_policies` | غير مُثبت بالكامل في الجدول دون تشغيل | حقول AI اقتراحات — يجب أن تبقى **قراءة/اقتراح** فقط |
| **Contracts & Catalog items** | **جزئي–متقدم** | متوسط | عقود، بنود خدمة، governance API | — | واجهات contracts + catalog |
| **Meetings / Approvals** | موجود | متوسط | محرك موافقات موسّع | migrations 2026_04_01_* | — |
| **Financial reconciliation** | موجود | عالٍ — بيانات مراجعة | جداول run/findings/history | تقارير تحت `backend/reports/` | — |
| **HR (Employees, attendance)** | **جزئي** | متوسط | فروع، شركات | تكاملات HR migrations | ليس HR مؤسسي كامل Wave 11 |
| **Plugins / API keys / Webhooks** | موجود | متوسط–عالٍ أمنياً | تشغيل، secrets | — | إعدادات تكامل |
| **ZATCA** | موجود (مسارات + نماذج) | عالٍ امتثال | فواتير، أرشفة | — | Wave 8 |
| **Notifications / Push** | موجود | منخفض–متوسط | أجهزة مستخدم | migration أجهزة دفع | — |
| **Intelligence / Command center** | **جزئي** | متوسط — يجب read-only للقرارات المالية | `IntelligenceCommandCenterGovernanceAudit`, أحداث مجال | — | فصل واضح بين مؤشرات وإجراءات |
| **Platform admin** | موجود | متوسط | صلاحيات owner/platform | مسارات admin | لوحة QA وطابور تسجيل |

---

## 4. النواة المالية الحالية (تعريف دقيق للمستودع)

1. **`App\Services\WalletService`** — نقطة الدخول لشحن المحفظة، التحويل، الانعكاسات؛ **idempotency** صريحة؛ ربط `invoice_id` / `payment_id` على `wallet_transactions` (Phase 3 migration).  
2. **`App\Services\LedgerService`** — ترحيل قيود متوازنة؛ `trace_id` إلزامي؛ لا تحديث/حذف للقيود المنشورة (منطق الخدمة + تريغرات على `journal_entries`).  
3. **`App\Support\Accounting\FinancialGlMapping` و `WalletGlMapping`** — قوالب أسطر القيود (مثلاً إيراد صافٍ 4100، ضريبة 2300، ذمم 1200/نقد 1010).  
4. **`PostPosLedgerJob` / أوامر reconciliation** — مسار ترحيل/مراجعة ما بعد البيع.  
5. **`CompanyReceivableLedger`** — طبقة ذمم موازية للتشغيل/التقارير.  
6. **فواتير + مدفوعات + حالة الفاتورة** (`Invoice`, `Payment`, enums الحالة) مرتبطة بتدفقات POS والتقارير.

---

## 5. الأجزاء المغلقة (لا تُعبث بدون ADR + اختبارات مالية + موافقة)

| المنطقة | الملفات / الجداول المؤشرة | السبب |
|---------|---------------------------|--------|
| ترحيل القيود وسلامة التوازن | `LedgerService`, `journal_entries`, `journal_entry_lines`, تريغرات `2024_01_01_000038_*` | منع فساد دفتر الأستاذ |
| حركات المحفظة وتراكم الأرصدة | `WalletService`, `wallet_transactions`, `customer_wallets` | أموال عملاء |
| خرائط GL للمحفظة والبيع | `FinancialGlMapping`, `WalletGlMapping` | خطأ تعيين حساب = خطأ مالي |
| Idempotency للعمليات المالية | `IdempotencyKey`, middleware `idempotent` | منع ازدواجية التحصيل |
| فهارس مسار POS | migrations `*_pos_hot_path_indexes` | الأداء جزء من الاعتمادية التشغيلية |
| تريغرات / سياسات DB للقيود | migrations غير قابلة للعبث العشوائي | سلامة بيانات |

---

## 6. طبقات الإضافة الآمنة (مقترحة معمارياً)

- **واجهات API جديدة** تحت namespaces واضحة (`Platform\Crm\`, `Platform\Governance\`) دون تعديل مسارات المحفظة الحرجة.  
- **نماذج CRM مؤسسية** مربوطة بـ `companies` / `customers` / `users` عبر مفاتيح أجنبية واضحة + جداول تاريخ (`*_histories`).  
- **طبقة قراءة للذكاء** (views موادلة، materialized لاحقاً بحرص) **بدون** كتابة على جداول مالية.  
- **Audit موحّد** يستند إلى `AuditLog` الحالي حيث يناسب، أو جداول تدقيق موازية لكل كيان جديد.

---

## 7. مراجعة العلاقات الحساسة (ملخص)

| الكيان | الجداول/النماذج | ملاحظات سلامة |
|--------|-----------------|-----------------|
| **users** | `users` + `company_id`, `branch_id`, `customer_id`, `org_unit_id` | `User::booted` يفرض تناسق المستأجر/الفرع/الوحدة التنظيمية |
| **companies** | `companies` | عند الإنشاء يُشغَّل `SystemChartOfAccountsSeeder::ensureForCompany` |
| **individuals (العملاء الأفراد)** | **`customers`** (ليس جدول `individuals` منفصل) | الحقل `type` / `CustomerType` يميّز الأفراد عن الأساطيل |
| **branches** | `branches` | مرتبطة بكل الكيانات التشغيلية |
| **subscriptions** | `subscriptions` | middleware `subscription` على المسارات الحساسة |
| **invoices** | `invoices`, `invoice_items` | مرتبطة بـ GL وZATCA والمحفظة |
| **wallets** | `wallets`, `customer_wallets`, `wallet_transactions` | append-only منطقياً؛ روابط فاتورة/دفع |
| **ledger** | `journal_entries`, `journal_entry_lines`, `chart_of_accounts` | immutability في الطبقة DB |

---

## 8. مخاطر تسمية واتساق (WAVE 0)

1. **تكرار مسارات API للمحفظة** في `routes/api.php`: مجموعات `wallet`، `wallets`، ومسارات `fleet` مكررة لنفس الكنترولر — خطر صيانة واختبار مزدوج.  
2. **دخول مزدوج في الواجهة:** `/login` و `/platform/login` — **WAVE 1** وضّح عقد التوجيه ورسائل الخطأ (`message_key`)؛ يبقى التقييم البصري/UX أوسع لموجات لاحقة.  
3. **مصطلح "Individual" في المتطلبات مقابل `Customer` في الكود** — يتطلب مسرد مصطلحات وواجهات مستخدم متسقة.  
4. **صلاحيات المحفظة مرتبطة أحياناً بـ `invoices.view` / `invoices.update`** — قد يكون مقصوداً لكنه يحتاج توثيقاً في مصفوفة الصلاحيات.

---

## 9. خارطة المخاطر (ملخص تنفيذي)

| ID | الخطر | الأثر | التخفيف المقترح |
|----|--------|-------|------------------|
| R1 | تعديل غير مُدار على `WalletService` / `LedgerService` | فساد مالي | منع التعديل إلا عبر PR منفصل + اختبارات تكامل + مراجعة محاسبية |
| R2 | ازدواج مسارات REST للمحفظة | سلوك غير متسق بين العملاء | توحيد تدريجي مع إبقاء التوافق للخلف |
| R3 | CRM/ذكاء يكتب على كيانات مالية | مخالفة سياسة read-only intelligence | فصل طبقات، سياسات في `Platform_Financial_Control_Policy.md` |
| R4 | migrations غير قابلة للتراجع على بيئات فيها بيانات | تعطيل نشر | مراجعة rollback + نسخ احتياطي قبل النشر |
| R5 | توسعة صلاحيات بدون SoD | احتيال داخلي | Wave 12 + مصفوفة الصلاحيات |

---

## 10. فجوات حقيقية مقابل الموجات (1–15) — ملخص

- **Wave 1:** **مُنجَز ومُغلَق (PR6).** أهلية الدخول، `account_context`، جلسات Sanctum + تدقيق، توجيه الواجهة بعد الدخول، وتقييد/إشارات أمنية أساسية — دون 2FA/geo/autonomous block. التفصيل في إغلاقات PR1–PR5 وملف الإغلاق النهائي.  
- **Wave 2–4:** كيانات أساسية موجودة؛ "ملف مؤسسي كامل" و CRM كامل و Ticketing enterprise يتطلب توسيع الجداول/الواجهات والسياسات.  
- **Wave 7:** محرك قيود موجود؛ **فترات مالية، إقفال، ترحيل accrual متقدم، خزينة، AR/AP كاملة** — فجوة كبيرة عن الموجود.  
- **Wave 9–10:** باقات وإشعارات موجودة جزئياً؛ HUB موحد للاتصالات والتكاملات يحتاج تصميم.  
- **Wave 11–15:** HR داخلي للمنصة، مركز حوكمة كامل، BI، بحث عالمي، تصليب تقني — غالبها تخطيط وتنفيذ تدريجي.

التفصيل في: [`Platform_Module_Status_Matrix.md`](./Platform_Module_Status_Matrix.md).

---

## 11. مصطلحات موحّدة (مسودة أولية)

| المصطلح في المتطلبات | المسمّى في الكود / المستودع |
|----------------------|------------------------------|
| Individual customer | `Customer` مع `CustomerType` مناسب |
| Company user (موظف شركة) | `User` مع `company_id` |
| Platform employee | مستخدم بصلاحيات إدارية/مالك منفصل عن المستأجر (يُوثَّق في Wave 1 حسب التنفيذ الفعلي) |
| Ledger / Journal | `journal_entries` + `LedgerService` |
| Wallet operational | `WalletService` + `wallet_transactions` |

---

## 12. خطة تنفيذ مرتبة (الموجات)

1. **WAVE 0** — مراجعة + وثائق.  
2. **WAVE 1** — هوية ودخول ووصول (**PASS / GO لـ WAVE 2** — راجع [`Platform_Wave1_Final_Closeout.md`](./Platform_Wave1_Final_Closeout.md)).  
3. **WAVES 2–6** — كيانات، CRM، تذاكر، تشغيل… بالترتيب الوارد في الطلب.  
4. **WAVE 7+** — محاسبة مؤسسية إضافية **فوق** النواة الحالية فقط.  
5. **WAVES 8–15** — امتثال، باقات، تكاملات، HR منصة، حوكمة، BI، بحث، استدامة.

لا انتقال لموجة **N+1** إلا بعد: اختبارات + مراجعة وظيفية/بصرية/صلاحيات + عدم كسر النواة المالية (انظر [`Platform_Testing_Gate.md`](./Platform_Testing_Gate.md) و [`Platform_Release_Readiness_Gate.md`](./Platform_Release_Readiness_Gate.md)).

---

## 13. إغلاق WAVE 0 — معايير PASS / FAIL

| المعيار | الحالة |
|---------|--------|
| Blueprint رئيسي | **PASS** — هذا الملف |
| خريطة مخاطر | **PASS** — القسم 9 |
| قائمة أجزاء مغلقة | **PASS** — القسم 5 |
| قائمة فجوات | **PASS** — القسم 10 |
| مصطلحات أولية | **PASS** — القسم 11 |
| خطة موجات | **PASS** — القسم 12 |
| تشغيل كامل لـ CI/اختبارات المستودع | **غير مطلوب لإغلاق Wave 0** — يُسجَّل في [`Platform_Testing_Gate.md`](./Platform_Testing_Gate.md) عند التشغيل |

**التوصية بعد WAVE 1:** **GO** لبدء **WAVE 2** وفق نطاق الموجة 2 فقط؛ **NO-GO** لأي تعديل على النواة المالية دون بوابة مالية واختبارات تكامل.

---

## 14. الوثائق المرافقة

| الملف | الغرض |
|-------|--------|
| [`Platform_Wave1_Final_Closeout.md`](./Platform_Wave1_Final_Closeout.md) | إغلاق رسمي لموجة الهوية والدخول (WAVE 1) |
| [`Platform_Module_Status_Matrix.md`](./Platform_Module_Status_Matrix.md) | تفصيل الحالة لكل وحدة |
| [`Platform_Testing_Gate.md`](./Platform_Testing_Gate.md) | بوابات اختبار لكل موجة |
| [`Platform_Permission_Matrix.md`](./Platform_Permission_Matrix.md) | أدوار وصلاحيات |
| [`Platform_Financial_Control_Policy.md`](./Platform_Financial_Control_Policy.md) | سياسات النواة المالية |
| [`Platform_UX_UI_Consistency_Guide.md`](./Platform_UX_UI_Consistency_Guide.md) | اتساق تجربة الاستخدام |
| [`Platform_Release_Readiness_Gate.md`](./Platform_Release_Readiness_Gate.md) | جاهزية Staging/Prod |

---

## 15. قائمة الملفات المُنشأة/المحدّثة (WAVE 0)

- `docs/Platform_Master_Execution_Blueprint.md` (هذا الملف)  
- `docs/Platform_Module_Status_Matrix.md`  
- `docs/Platform_Testing_Gate.md`  
- `docs/Platform_Permission_Matrix.md`  
- `docs/Platform_Financial_Control_Policy.md`  
- `docs/Platform_UX_UI_Consistency_Guide.md`  
- `docs/Platform_Release_Readiness_Gate.md`  

**Migrations:** لا شيء في WAVE 0.  
**ما لم يُلمس:** أي منطق تطبيق (`*.php` تطبيقية، `*.vue`، `*.ts` باستثناء هذا التوثيق).

---

*مرجع إضافي داخل المستودع:* [`Platform_Safe_V1_Report.md`](./Platform_Safe_V1_Report.md) إن وُجد ويتماشى مع سياسات الأمان الحالية.

---

## 16. إغلاق WAVE 1 (PR6) — ملخص

| المعيار | الحالة |
|---------|--------|
| مخرجات PR1–PR5 موثّقة | **PASS** — إغلاقات PR في `docs/Platform_Wave1_PR*_Closeout.md` |
| Regression gate (هوية/جلسات/OTP/تقييد) | **PASS** — انظر [`Platform_Testing_Gate.md`](./Platform_Testing_Gate.md) §4.1 |
| حكم WAVE 1 | **PASS** |
| حكم البدء بـ WAVE 2 | **GO** |

التفصيل الكامل: [`Platform_Wave1_Final_Closeout.md`](./Platform_Wave1_Final_Closeout.md).
