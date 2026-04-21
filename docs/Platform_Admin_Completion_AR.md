# موجة استكمال لوحة مشغّل المنصة — ملخص تنفيذي

## Phase 3 Final Cleanup & Closure — **مغلقة (CLOSED)**

- **ما الذي تم تفكيكه في Phase 3 (عرض فقط، `props` / `emits`، بلا API ولا `router` ولا `toast` داخل الأقسام):**
  - **الخطوة 1 — overview:** `PlatformAdminOverviewSection.vue`
  - **الخطوة 2 — companies (جدول):** `PlatformAdminCompaniesSection.vue` + `platformCompanyPatch.ts` (مفاتيح داخلية يفسّرها الأب فقط)
  - **الخطوة 3 — operator-commands:** `PlatformAdminOperatorCommandsSection.vue` (النسخ عبر emit → الصفحة)
  - **الخطوة 4 — governance:** `PlatformAdminGovernanceSection.vue` (`GET /system/version` و`ensureSectionData` في الصفحة)
  - **الخطوة 5 — finance:** `PlatformAdminFinanceSection.vue`
  - **الخطوة 6 — audit:** `PlatformAdminAuditSection.vue`
  - **إغلاق Phase 3 — قسم تشغيل خفيف:** `PlatformAdminOpsSection.vue` (ملخص التشغيل؛ التحميل و`toast` عند الفشل والتوجيه إلى QA / أوامر المشغّل يبقى في الصفحة)
- **ما الذي بقي داخل `PlatformAdminDashboardPage.vue` ولماذا:**
  - **تنسيق وتشغيل:** `fetchData`، `loadOpsSummary`، `loadAuditLogs`، `loadPlatformCustomers`، `loadCancellationRequests`، `loadPlatformBannerAdmin`، `ensureSectionData`، حراسة `auth.isPlatform`، `sectionVisible(sectionKey)`، التوجيه (`goToPlatformSection`، `openPlatformQa`)، `platformListsBootstrapped`.
  - **أقسام ما زالت inline** لأنها مدمجة بحالة كثيرة أو نماذج/ـ modals (`tenants` مع فلاتر + `PlatformAdminCompaniesSection`، `customers`، `plans` + modal التعديل، `cancellations` + teleports، `banner`/إعلان المنصة + حفظ)، ومودالات Teleport (`tenantOps`، `financialEdit`، `cancelAction`) — **مالك للـ state والـ API** دون تغيير عقود.
- **تأكيد:** لم يُمسّ الـ Ledger ولا عقود الـ API الحساسة في إغلاق Phase 3؛ التغييرات واجهة/تنظيم ملفات فقط.
- **Phase 3 = CLOSED** بعد نجاح `npm run type-check` و`npm run test` على الواجهة.

## Phase 4 — QA Hardening & Stability Gate — **مغلقة (CLOSED)**

- **اختبارات Frontend (Vitest) المضافة أو الموسّعة:**
  - `src/config/platformAdminNav.test.ts` — تغطية `platformPathFromAdminHash` لكل `#admin-section-*` والقيم الافتراضية؛ تزامن `platformAdminNavItems` مع أسماء مسارات المنصة الـ 11.
  - `src/router/platformRoutesContract.test.ts` — `router.resolve({ name })` لكل مسار من القائمة؛ سجلات `getRoutes()` لـ `/admin/overview` و`/admin` (دالة تحويل) ووجود `platform-overview`.
  - `src/components/platform-admin/sections/platformAdminSectionsArch.test.ts` — فحص بنيوي: لا `apiClient` / `useRouter` / `useToast` / `navigator.clipboard` في ملفات `sections/*.vue|ts`.
  - توسيع `src/views/platform/platformViewsLoad.test.ts` — استيراد ديناميكي لكل مكوّنات الأقسام المفككة للتأكد من عدم كسر lazy/البناء.
- **اختبارات E2E (Playwright):**
  - `e2e/platform-phase4-stability.spec.ts` — دخول منصة، المرور على كل مسار `/platform/*` مع التحقق من `#admin-section-*`، فتح مباشر لـ companies/finance/audit، تحويلات `/admin` و`/admin/overview` و`/admin#admin-section-audit`، دخان لأزرار تحميل التدقيق ونسخ الأمر.
  - تحديث `e2e/platform-phase2-admin-ui-smoke.spec.ts` — مطابقة النص الفرعي للوحة التنفيذية (`مركز قيادة تنفيذي`) بدل نص قديم غير موجود في الواجهة.
- **`data-testid`:** `platform-admin-root` و`platform-admin-main` في `PlatformAdminLayout.vue` لدعم الـ E2E دون تغيير سلوك المستخدم.
- **Regressions:** لا تغيير على عقود الـ API أو الـ Ledger؛ الإصلاحات ضمن نطاق الاختبارات والتوافق مع النص الحالي فقط.
- **أوامر الإغلاق:** `npm run type-check`، `npm run test`، و`npm run test:e2e` (يحتاج بيئة تشغيل وخادمًا يقبل تسجيل الدخول التجريبي لحساب المنصة عند استخدام `vite preview` الافتراضي).
- **Phase 4 = CLOSED** بعد نجاح الاختبارات أعلاه في بيئة التشغيل المعتمدة.

## Final Blocking Closure Check — Phase 4

### 1) نتيجة Final Platform Blocking E2E

| البند | الحالة |
|--------|--------|
| ملف السيناريو الإلزامي | `e2e/platform-phase4-final-blocking.spec.ts` |
| النتيجة في آخر تشغيل معتمد | **Passed** |
| الأمر | `npm run test:e2e` (يشمل كل ملفات `e2e/`؛ مشروع Playwright `chromium-platform-gate` يشغّل اختبارات المنصة بـ `workers: 1` لتفادي تداخل جلسة حساب التجربة) |

**ما تم التحقق منه في السيناريو النهائي (ملخص):**

1. تسجيل الدخول بحساب منصة (`PW_PLATFORM_EMAIL` / `PW_PLATFORM_PASSWORD` أو الافتراضيات التجريبية).
2. `/platform/overview` + إعادة تحميل + ظهور `#admin-section-overview` و`data-testid` للتخطيط.
3. التنقّل بالترتيب: companies → operator-commands → governance → finance → audit → announcements → cancellations مع بقاء `platform-admin-main` ظاهراً.
4. فتح مباشر: `/platform/companies`، `/platform/finance`، `/platform/audit`.
5. إجراءات: نسخ من operator-commands (عند توفر الزر)، فتح modal المالية عبر «تحديث القرار» ثم «إلغاء»، زر «تحميل» في التدقيق، انتظار نتيجة إصدار النظام في الحوكمة (`env=` / `ver=` أو رسالة خطأ واضحة)، فتح نافذة «تشغيل —» من جدول المشتركين عند توفر الصف ثم إغلاقها.
6. تصفية مالية اختيارية (`pending_platform_review`) دون تغيير سلوك العمل.
7. توافق: `/admin` → overview، `/admin/overview` → overview، ثلاثة روابط hash قديمة (`finance`، `tenants`، `governance`).
8. رصد `pageerror` فقط كفشل صريح (لا يُفرض فحص كل رسائل `console` غير القاتلة).

### 2) قرار نبض الجدولة في الخلفية (البند B)

**الخيار المعتمد: الخيار 1 — إبقاء كاستثناء موثّق**

- **الملفات:** `backend/routes/console.php` (مهمة `Schedule::call` كل دقيقة تكتب الطابع في الكاش)، و`backend/app/Services/Platform/PlatformAdminOverviewService.php` (قراءة المفتاح `platform:schedule:last_run_at` لحقل `health.scheduler_last_run_at` الموجود مسبقاً في الاستجابة).
- **التصنيف:** خارج النطاق الأصلي لـ «QA Hardening» الصرف؛ **استثناء تشغيلي معتمد** لتحسين مصدر قيمة كانت تُعاد دائماً `null` دون تغيير شكل عقد JSON.
- **القيود:** لا مساس بالـ Ledger؛ لا تغيير لعقود API شكلية (نفس المفتاح والنوع؛ القيمة قد تصبح سلسلة ISO عند عمل cron).
- **لا يُعدّ جزءاً من Phase 4 بصمت:** يُذكر هنا صراحةً كاستثناء منفصل عن اختبارات الواجهة.

### 3) إصلاحات مسموحة لإغلاق المانع فقط (بدون توسيع نطاق)

- **`playwright.config.ts`:** مشروعان — `chromium` (باستثناء ملفات منصة phase2/4) و`chromium-platform-gate` (serial، عامل واحد) لمنع تعارض تسجيل دخول المنصة.
- **`e2e/production-real-flow.spec.ts`:** محددات حقول الدخول تتوافق مع `LoginView` (`type="text"` + `autocomplete="username"`)؛ تجاوز الاختبار عند غياب `PW_LOGIN_EMAIL` / `PW_LOGIN_PASSWORD`.
- **`e2e/staff-logout.spec.ts`:** اختبار الشاشة الصغيرة يستخدم `data-testid="app-header-logout"` ومهلة أطول لأن التسمية المرئية لـ «تسجيل الخروج» مخفية تحت `sm`.

### 4) الحكم النهائي

> **Phase 4 = CLOSED**

بعد: نجاح `npm run type-check`، `npm run test` (85)، `npm run test:e2e` (24 ناجحة، 4 متجاوزة بسبب متغيرات بيئة اختيارية)، وسيناريو الإغلاق النهائي للمنصة **Passed**، وحسم نبض الجدولة كاستثناء موثّق أعلاه.

## Phase 3 — تفكيك داخلي (مرجع تاريخي — مُكمَّل أعلاه)

- **الخطوة 1 (overview):** `PlatformAdminOverviewSection.vue` تحت `frontend/src/components/platform-admin/sections/` — يستقبل بيانات القراءة من `PlatformAdminDashboardPage.vue` ويُصدِر `go-section` / `open-tenant` دون تغيير عقود الـ API أو مسارات التوجيه.
- **الخطوة 2 (companies):** `PlatformAdminCompaniesSection.vue` + `platformCompanyPatch.ts` — جدول المشتركين فقط؛ `companies` و`loading` كما طُلب، مع props اختيارية للعرض (`companiesFeedOk`, `canOperateCompanies`, `companyOpBusyId`) دون أي استدعاء API من المكوّن. أحداث التعليق/التفعيل تمر عبر `go-section` بمفتاح داخلي يفسّره الأب ثم يستدعي `patchCompanyOperational` كما سابقًا.
- **الخطوة 3 (operator-commands):** `PlatformAdminOperatorCommandsSection.vue` — عرض البطاقات فقط؛ `commands` من `operatorCommandsForDisplay` في الصفحة؛ زر النسخ يُصدِر `copy-command` والصفحة تنفّذ `copyOperatorCommand` (clipboard + toast) دون تغيير النصوص أو الأوامر.
- **الخطوة 4 (governance):** `PlatformAdminGovernanceSection.vue` — نفس محتوى الحوكمة السابق (`PlatformAdminScopeHeader` مُستبدَل)؛ `GET /system/version` و`ensureSectionData('governance')` في الصفحة فقط.
- **الخطوة 5 (finance):** `PlatformAdminFinanceSection.vue` — عرض النموذج المالي والجدول؛ الـ modal و`saveFinancialModel` في الصفحة.
- **الخطوة 6 (audit):** `PlatformAdminAuditSection.vue` — عرض التدقيق؛ `loadAuditLogs` والفلترة في الصفحة.

## إغلاق رسمي — Phase 2 (**Platform Route Views Established**)

- **الحالة:** مقبول للدمج (**APPROVED FOR MERGE**) بعد نجاح `npm run type-check` و`npm run test` (65/65) وعدم المساس بالـ Ledger أو عقود الـ API الحساسة.
- **وسم داخلي مقترح (بعد الدمج على الفرع الرئيسي):** `platform-phase-2-routes` — أو سطر في release notes: «Phase 2: استقرار `/platform/*` — ملف View لكل مسار، `sectionKey`، إزالة `meta.platformSection`».
- **ما بعد النشر (إلزامي للتشغيل):** مراقبة التنقّل الجانبي، والروابط المباشرة لكل `/platform/...`، وتحويلات `/admin` + `#admin-section-*`.
- **تذكير:** الإغلاق **مرحلي** بالنسبة لـ Phase 2 — أما Phase 3 فقد فُصِلت أقسام العرض الرئيسية (انظر أعلى) مع بقاء التشغيل والنماذج الثقيلة في الصفحة؛ ليس إعلانًا بـ «Platform OS» مكتمل.

## خريطة مسارات الواجهة (`frontend/src/router/index.ts`)

| المسار | اسم المسار (route name) | ملف الـ View |
|--------|-------------------------|---------------|
| `/platform` | — | إعادة توجيه إلى `platform-overview` |
| `/platform/overview` | `platform-overview` | `PlatformOverviewView.vue` |
| `/platform/governance` | `platform-governance` | `PlatformGovernanceView.vue` |
| `/platform/ops` | `platform-ops` | `PlatformOpsView.vue` |
| `/platform/companies` | `platform-companies` | `PlatformCompaniesView.vue` |
| `/platform/customers` | `platform-customers` | `PlatformCustomersView.vue` |
| `/platform/plans` | `platform-plans` | `PlatformPlansView.vue` |
| `/platform/operator-commands` | `platform-operator-commands` | `PlatformOperatorCommandsView.vue` |
| `/platform/audit` | `platform-audit` | `PlatformAuditView.vue` |
| `/platform/finance` | `platform-finance` | `PlatformFinanceView.vue` |
| `/platform/cancellations` | `platform-cancellations` | `PlatformCancellationsView.vue` |
| `/platform/announcements` | `platform-announcements` | `PlatformAnnouncementsView.vue` |

## Phase 2 — Views مستقلة لكل مسار (إغلاق تقني محدود)

- **ملف لكل مسار:** تحت `frontend/src/views/platform/` (`PlatformOverviewView.vue`, `PlatformCompaniesView.vue`, …) مع ربط الراوتر دون `meta.platformSection`.
- **صفحة مشتركة للمحتوى:** `PlatformAdminDashboardPage.vue` تستقبل `sectionKey` وتعرض القسم المطابق فقط؛ نفس منطق البيانات وواجهات الـ API السابقة.
- **أداء التنقّل:** `platformListsBootstrapped` يمنع إعادة `fetchData` + `loadOpsSummary` عند كل تغيير مسار داخل المنصة في الجلسة الواحدة.
- **مكوّن:** `PlatformPlanBadge.vue` مستخرج من التعريف السابق داخل الملف الأحادي.
- **اختبار:** `platformViewsLoad.test.ts` يتحقق من تحميل كل وحدات الـ View (بيئة `happy-dom` لأن استيراد Vue يتطلب DOM).

## Phase 1 — مسارات واجهة المنصة (`/platform/*`) — **وصف تاريخي**

- **Layout مستقل:** `PlatformAdminLayout.vue` — شريط جانبي يعتمد على `RouterLink`، مسار تفصيلي (breadcrumb)، تنقّل جوّال عبر قائمة منسدلة.
- **ما قبل Phase 2:** كان كل مسار `/platform/...` يمر عبر `meta.platformSection` وعرض واحد (`AdminDashboardView.vue`) — **أُلغي** لصالح ملف View لكل مسار و`sectionKey` في `PlatformAdminDashboardPage.vue` (انظر أعلى الملف وخريطة المسارات).
- **تحويلات آمنة:** `/admin` و`/admin/overview` يُعاد توجيههما إلى `/platform/...` (مع دعم `#admin-section-*` القديمة عبر `platformPathFromAdminHash`). تبقى `/admin/qa` و`/admin/registration-profiles` كما هي.
- **بدون تغيير خادم أو Ledger:** لا تعديل على واجهات المحاسبة أو العقود المالية.

## ما تم إضافته (استكمال لوحة المنصة)

- **واجهة برمجية:** `GET /api/v1/platform/customers` ضمن حماية `platform.admin` وصلاحية `platform.companies.read` — قائمة عملاء المستأجرين عبر المنصة مع بحث (`q`) وتصفية (`status`, `company_id`) وتصفح.
- **قسم واجهة «عملاء المنصة»:** جدول فعلي مع حالات التحميل / الخطأ / الفراغ والتصفح، دون استدعاء واجهات المستأجر.
- **جدول المشتركين (شركات):** عمود **حالة الاشتراك** (عربي)، وإجراءات **تعليق** و**إعادة تفعيل** عبر `PATCH /api/v1/platform/companies/{id}/operational` عند توفر `platform.companies.operational`.
- **تفسير تشغيلي قصير** و**أوامر مشغّل سياقية** و**Health موسّع** و**تنبيهات/رؤى بالعربية من الخادم** (حسب الملفات المرتبطة في هذه المرحلة).

## ما تم تحسينه

- **التدقيق:** عند الفشل تظهر رسالة داخل القسم («تعذر تحميل سجلات التدقيق») مع الإبقاء على التنبيه المنبثق.
- **التنبيهات والرؤى:** عدم اختفاء المكوّن عند الفراغ؛ نصوص عربية للحالة الفارغة.
- **Health:** مؤشرات إضافية (Redis، عمق الطابور، المهام الفاشلة، ملاحظة الجدولة) حسب توفر البيانات في الخادم.

## كيف يعمل (اختصار)

1. **العملاء:** من واجهة المنصة (`/platform/customers`)، القسم يستدعي `GET /platform/customers` (مع بادئة `api/v1` في العميل). النتائج معروضة بالعربية؛ التعديل التفصيلي للعميل يبقى من بوابة فريق العمل بسياق شركة.
2. **الشركات:** قائمة الشركات من `GET /admin/companies`؛ عمود الاشتراك يقرأ `subscription_status` من الاستجابة؛ التعليق يضبط `status: suspended` وإعادة التفعيل `status: active` مع `is_active: true` دون تغيير منطق الفوترة.
3. **الصلاحيات:** قراءة الشركات/العملاء تتطلب `platform.companies.read`؛ الإجراءات التشغيلية للشركة تتطلب `platform.companies.operational`.

## التحقق السريع

- `npm run type-check` في مجلد `frontend`.
- استدعاء `GET /api/v1/platform/customers` بحساب مشغّل منصة مصرّح له.
