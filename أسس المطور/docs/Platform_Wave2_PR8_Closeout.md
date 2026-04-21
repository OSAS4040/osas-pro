# WAVE 2 / PR8 — Closeout: Platform Reports (Read-only)

**التاريخ:** 2026-04-12  
**النتيجة:** PASS — `docker exec saas_app php artisan test tests/Feature/Reporting/`

---

## 1) ملخص

أُضيف تقرير منصّة **`platform.pulse_summary`** بنفس عقد PR7 (Query + Reporter + Request + Controller + `ReportingApiEnvelope`). الوصول **لمشغّلي المنصّة فقط** عبر `SaasPlatformAccess::isPlatformOperator` (مطابقة لـ `/platform/ops-summary`). التقرير يقدّم **summary** (شركات، مستخدمون، عملاء، فروع، اشتراكات، تذاكر مفتوحة/متأخرة، أوامر عمل في الفترة) و**breakdowns** حسب الحالة، النشاط (إنشاءات داخل الفترة)، والأسابيع (`date_trunc`) مع حدّ **`platform_max_time_buckets`**. لا قوائم تفصيلية ثقيلة ولا تصدير.

---

## 2) الملفات

| الملف |
|-------|
| `backend/config/reporting.php` (مفتاح `platform_max_time_buckets`) |
| `backend/app/Reporting/Queries/PlatformPulseSummaryQuery.php` |
| `backend/app/Services/Reporting/PlatformPulseSummaryReporter.php` |
| `backend/app/Http/Requests/Reporting/PlatformReportingPulseRequest.php` |
| `backend/app/Http/Controllers/Api/V1/Reporting/PlatformReportingController.php` |
| `backend/routes/api.php` |
| `backend/tests/Feature/Reporting/PlatformReportingPulseTest.php` |
| `docs/Platform_Wave2_PR8_Closeout.md` |

---

## 3) migrations

**لا يوجد.**

---

## 4) التغييرات

- **`GET /api/v1/reporting/v1/platform/pulse-summary?from=Y-m-d&to=Y-m-d`** — خارج middleware `reports.*`؛ يتطلب `auth:sanctum` + سياق المستأجر الحالي كباقي المجموعة، ثم **403** إن لم يكن البريد ضمن `saas.platform_admin_emails`.
- **استعلامات:** `DB::table` / `Company` على نطاق عالمي مع `whereNull(deleted_at)` حيث ينطبق؛ لا اعتماد على `HasTenantScope` لعرض المنصّة.
- **`summary`:** إجمالي الشركات، تشغيلية (`active` + `is_active`)، موقوفة (`suspended`)، `other` (متبقي)، مستخدمون، عملاء، فروع، اشتراكات، تذاكر (إن وُجد الجدول)، أوامر عمل في الفترة.
- **`breakdown`:** `by_status` (شركات / اشتراكات / تذاكر)، `by_activity`، `by_time_period` (أسابيع، محدودة بالإعداد).

---

## 5) الاختبارات

- `PlatformReportingPulseTest`: 403 لغير المنصّة، شكل العقد، ازدياد عدد الشركات بعد إنشاء شركة، تحقق نطاق التاريخ، رفض تقني.

---

## 6) PASS / FAIL

**PASS** — 12 tests في `tests/Feature/Reporting/` (يشمل اختبارات PR7 + PR8).

---

## 7) المخاطر

- الاعتماد على **PostgreSQL** لـ `date_trunc` في السلاسل الزمنية؛ بيئات غير Postgres تحتاج بديل عند النشر.
- توسيع نطاق المنصّة يزيد تكلفة الاستعلام؛ راقب الحمل عند توسيع الفترة أو إضافة تقارير.

---

## 8) GO / NO-GO لـ PR9

**GO** — يمكن إضافة تقارير منصّة/مستأجر إضافية على نفس الطبقة مع الحفاظ على حدود الأداء وعقود JSON.
