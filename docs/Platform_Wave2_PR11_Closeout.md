# WAVE 2 / PR11 — Closeout: Global Operations Feed + Command Center UX

**التاريخ:** 2026-04-12  
**النتيجة:** PASS — التحقق من الواجهة الأمامية محلياً؛ التحقق من الـ backend عبر Docker (`saas_app`).

---

## 1) ملخص تنفيذي

أُنجز **Global Operations Feed** كقراءة فقط على مسار **`GET /api/v1/reporting/v1/operations/global-feed`** بنمط WAVE 2 (**Form Request → Reporter → Query → Controller → `ReportingApiEnvelope`**)، مع **عزل شركة/فرع**، **تقييد مالي** عبر **`reports.financial.view`**، **ترقيم صفحات وحد أقصى لـ `per_page`**، و**قواعد انتباه/وسوم** في الـ presenter.  
أُضيفت واجهة **Command Center** في الواجهة الأمامية (`/operations/global-feed`): رأس، شريط انتباه، بطاقات ملخص، **تجميع زمني (اليوم / أمس / سابقاً)**، فلاتر جانبية، حالات تحميل/فارغ/خطأ، وربط من صفحة التقارير. لا mutations ولا مساس بالنواة المالية.

---

## 2) الملفات الجديدة والمعدّلة

### Backend (تقارير PR11)

| الملف |
|-------|
| `backend/config/reporting.php` (مفاتيح `global_feed_*`) |
| `backend/app/Reporting/Queries/GlobalOperationsFeedQuery.php` |
| `backend/app/Services/Reporting/GlobalOperationsFeedReporter.php` |
| `backend/app/Http/Requests/Reporting/GlobalOperationsFeedRequest.php` |
| `backend/app/Http/Controllers/Api/V1/Reporting/GlobalOperationsFeedController.php` |
| `backend/app/Reporting/Operations/OperationFeedItemPresenter.php` |
| `backend/app/Reporting/Operations/OperationFeedEntityRouteResolver.php` |
| `backend/routes/api.php` (مسار `global-feed`) |
| `backend/tests/Feature/Reporting/GlobalOperationsFeedTest.php` |

### Frontend (واجهة PR11)

| الملف |
|-------|
| `frontend/src/types/globalOperationsFeed.ts` |
| `frontend/src/composables/useGlobalOperationsFeed.ts` |
| `frontend/src/utils/groupOperationsFeedByDay.ts` |
| `frontend/src/utils/groupOperationsFeedByDay.test.ts` |
| `frontend/src/components/operations-feed/OperationsFeedHeader.vue` |
| `frontend/src/components/operations-feed/OperationsFeedSummaryCards.vue` |
| `frontend/src/components/operations-feed/OperationsFeedAttentionStrip.vue` |
| `frontend/src/components/operations-feed/OperationsFeedFilterSidebar.vue` |
| `frontend/src/components/operations-feed/OperationsFeedItemCard.vue` |
| `frontend/src/components/operations-feed/OperationsFeedTimelineGroup.vue` |
| `frontend/src/components/operations-feed/OperationsFeedEmptyState.vue` |
| `frontend/src/components/operations-feed/OperationsFeedErrorState.vue` |
| `frontend/src/views/operations/GlobalOperationsFeedView.vue` |
| `frontend/src/router/index.ts` |
| `frontend/src/views/reports/ReportsView.vue` |

### توثيق الإغلاق

| الملف |
|-------|
| `docs/Platform_Wave2_PR11_Closeout.md` (هذا الملف) |

---

## 3) migrations

**لا يوجد.**

---

## 4) ما تغيّر وظيفياً

- API موحّد للـ feed التشغيلي مع **`data.summary`** و **`data.items[]`** و **`meta`** (فلاتر، ترقيم، `financial_metrics_included`, `source_entities_included`, `generated_at`, `read_only`).
- شاشة **`/operations/global-feed`** للمستخدمين ذوي **`reports.view` + `reports.operations.view`**؛ المبالغ تُعرض في الواجهة فقط عندما يسمح الـ API (`financial_metrics_included` / `financial_visibility_applied`).
- رابط سريع من **التقارير** إلى مركز العمليات.

---

## 5) ما لم يُمس

- النواة المالية، الـ ledger، المحافظ، تدفقات المحاسبة.
- مسارات **`/api/v1/reports/*`** القديمة (لم تُستبدل).
- لا توسيع PR7–PR10 خارج نطاق الـ feed والواجهة المرتبطة به.

---

## 6) نتائج التحقق

| التحقق | الأمر / الملاحظة | النتيجة |
|--------|------------------|---------|
| Frontend type-check | `npm run type-check` (مجلد `frontend/`) | **PASS** |
| Frontend tests | `npm run test` (Vitest، يشمل `groupOperationsFeedByDay.test.ts`) | **PASS** (52 tests) |
| Backend — global feed | `docker exec saas_app php artisan test tests/Feature/Reporting/GlobalOperationsFeedTest.php` | **PASS** (8 tests, 41 assertions) |
| Backend — كامل `tests/Feature/Reporting/` (بعد PR12 export smoke) | `docker exec saas_app php artisan test tests/Feature/Reporting/` | **PASS** (40 tests) |

**ملاحظة بيئة:** على الجهاز المضيف قد لا يكون `php` في `PATH`؛ التنفيذ الموثوق للاختبارات يتم عبر **`docker exec saas_app`** كما في PR9.

---

## 7) PASS / FAIL الإجمالي

**PASS** — PR11 جاهز للإغلاق والانتقال إلى PR12.

---

## 8) المخاطر المتبقية

- تجميع **اليوم/أمس** في الواجهة يعتمد على **التاريخ المحلي** للمتصفح.
- تصدير الـ feed لصفحة واحدة (إن وُجد لاحقاً في PR12) يحتاج حداً صريحاً لعدد الصفوف حتى لا يُحمّل نافذة زمنية كاملة دفعة واحدة.
- تحسين **pickers** بدل المعرفات الرقمية الخام: مسجل كتحسين لاحق ولا يمنع الإغلاق.

---

## 9) GO / NO-GO لـ PR12

**GO** — يمكن البدء في **WAVE 2 / PR12 — Export Layer** (تصدير منظم للتقارير ضمن نطاق WAVE 2، read-only، مع احترام الفلاتر والصلاحيات).
