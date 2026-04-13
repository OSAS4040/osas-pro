# WAVE 2 / PR9 — Closeout: Company Reports (Read-only)

**التاريخ:** 2026-04-12  
**النتيجة:** PASS — `docker exec saas_app php artisan test tests/Feature/Reporting/`

---

## 1) ملخص

أُضيف تقرير **`company.pulse_summary`** على مستوى الشركة بنفس طبقة PR7: **ResolveReportingContextAction** + **ReportingContext** (فرع/عميل/مستخدم مع **cross_branch_access**)، **CompanyPulseSummaryQuery**، **CompanyPulseSummaryReporter**، **CompanyReportingPulseRequest**، **CompanyReportingController**، و**ReportingApiEnvelope**. الوصول عبر **`reports.view` + `reports.operations.view`** (ليس platform-only). المقاييس المالية (فواتير/مدفوعات في الفترة) تُحسب فقط عند **`reports.financial.view`** مع `meta.financial_metrics_included`. لا قوائم تفصيلية ثقيلة ولا تصدير.

---

## 2) الملفات

| الملف |
|-------|
| `backend/app/Reporting/Queries/CompanyPulseSummaryQuery.php` |
| `backend/app/Services/Reporting/CompanyPulseSummaryReporter.php` |
| `backend/app/Http/Requests/Reporting/CompanyReportingPulseRequest.php` |
| `backend/app/Http/Controllers/Api/V1/Reporting/CompanyReportingController.php` |
| `backend/routes/api.php` |
| `backend/tests/Feature/Reporting/CompanyReportingPulseTest.php` |
| `docs/Platform_Wave2_PR9_Closeout.md` |

---

## 3) migrations

**لا يوجد.**

---

## 4) التغييرات

- **`GET /api/v1/reporting/v1/company/pulse-summary`** — نفس مجموعة middleware التقارير التشغيلية مع PR7.
- **`data.summary`:** مستخدمون، عملاء، فروع، أوامر عمل في الفترة، فواتير/مدفوعات (حسب الصلاحية)، تذاكر مفتوحة/متأخرة.
- **`data.breakdown`:** `by_branch`, `by_status`, `by_activity`, `by_time_period` (أسابيع، محدودة بـ `reporting.platform_max_time_buckets`).
- **عزل شركة:** كل استعلام يفرض `company_id` للمستأجر؛ لا `SaasPlatformAccess`.

---

## 5) الاختبارات

`CompanyReportingPulseTest`: مالك، تقني 403، عدم تأثر عدد المستخدمين بشركة أخرى، فرع واحد لموظف بلا cross-branch، بريد منصّة مع صلاحيات تقارير، viewer بدون مالية، فاتورة للمالك، فرع غير صالح 422.

---

## 6) PASS / FAIL

**PASS** — 20 tests تحت `tests/Feature/Reporting/` (PR7 + PR8 + PR9).

---

## 7) المخاطر

- تذاكر بـ `branch_id` فارغ قد لا تظهر في تقارير مُقيّدة بالفرع فقط.
- سلاسل زمنية تعتمد على PostgreSQL `date_trunc`.

---

## 8) GO / NO-GO لـ PR10

**GO** — يمكن إضافة تقارير شركة إضافية على نفس الطبقة مع الحفاظ على حدود الأداء وصلاحيات التقارير الفرعية.
