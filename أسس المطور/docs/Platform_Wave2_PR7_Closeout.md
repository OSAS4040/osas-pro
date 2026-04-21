# WAVE 2 / PR7 — Closeout: Reporting Foundation (Read-only)

**التاريخ:** 2026-04-12  
**النتيجة:** PASS (بعد `php artisan test tests/Feature/Reporting/ReportingWorkOrderSummaryTest.php` في Docker)

---

## 1) ملخص تنفيذي

أُنشئت طبقة **`App\Reporting`** (نماذج قراءة، نطاق زمني، غلاف JSON موحّد) مع **`App\Reporting\Queries`** للاستعلامات المعزولة، و**`App\Actions\Reporting`** لبناء **سياق التقرير** من المستخدم والفلاتر مع التحقق من الشركة/الفرع/العميل/المستخدم، و**`App\Services\Reporting`** للتنسيق. تمت إضافة **`GET /api/v1/reporting/v1/operations/work-order-summary`** كأول endpoint موحّد: تجميع **aggregate** لأوامر العمل حسب `status` دون fan-out لصفوف تفصيلية (سلامة الاستعلام). التصدير الفعلي غير مُفعّل؛ الحقل `export` يوثّق التوسعة المستقبلية فقط. لا تعديل على ledger أو wallets أو منطق مالي جديد، ولا تغيير على جداول/مسارات **customers** كمنتج — التحقق من `customer_id` قراءة فقط للفلترة.

---

## 2) الملفات

| الملف |
|-------|
| `backend/config/reporting.php` |
| `backend/app/Reporting/ReportingDateRange.php` |
| `backend/app/Reporting/ReportingContext.php` |
| `backend/app/Reporting/ReportingApiEnvelope.php` |
| `backend/app/Reporting/Queries/WorkOrderOperationalSummaryQuery.php` |
| `backend/app/Actions/Reporting/ResolveReportingContextAction.php` |
| `backend/app/Services/Reporting/WorkOrderOperationalSummaryReporter.php` |
| `backend/app/Http/Requests/Reporting/ReportingWorkOrderSummaryRequest.php` |
| `backend/app/Http/Controllers/Api/V1/Reporting/ReportingController.php` |
| `backend/routes/api.php` |
| `backend/tests/Feature/Reporting/ReportingWorkOrderSummaryTest.php` |
| `docs/Platform_Wave2_PR7_Closeout.md` |

---

## 3) migrations

**لا يوجد.**

---

## 4) التغييرات وظيفياً

- **عقد API موحّد:** `report` (metadata + `export` placeholder) + `data` + `meta` + `trace_id`.
- **فلترة:** `from` / `to` (حد أقصى من `config/reporting.php`)، `branch_id`، `customer_id`، `user_id` — مع ربط صارم بـ `company_id` للمستأجر وصلاحية `cross_branch_access` للفرع.
- **استعلام:** `company_id` يُفرض صراحةً في طبقة الـ Query مع `withoutGlobalScope('tenant')` + شرط الشركة (defense in depth).
- **صلاحيات المسار:** `reports.view` + `reports.operations.view` (مثل تقارير العمليات الحالية).

---

## 5) الاختبارات

- `tests/Feature/Reporting/ReportingWorkOrderSummaryTest.php` — عزل مستأجر، منع تقني بلا صلاحيات تقارير، رفض عميل شركة أخرى، تحقق نطاق التاريخ، فرع واحد لموظف بلا cross-branch، فلتر `user_id`.

---

## 6) PASS / FAIL

**PASS** — 7 tests, 30 assertions.

---

## 7) المخاطر المتبقية

- التقارير القديمة تحت `/api/v1/reports/*` ما زالت خارج هذه الطبقة؛ يجب نقل التقارير الجديدة تدريجياً لتفادي ازدواجية السلوك.
- عند إضافة تقارير تفصيلية لاحقاً يجب فرض `max_detail_rows` وترقيم الصفحات صراحةً.

---

## 8) GO / NO-GO لـ PR8

**GO** — الأساس جاهز لتقارير إضافية read-only على نفس النمط (Query + Reporter + Request + غلاف JSON) دون لمس النواة المالية.
