# WAVE 2 / PR12 — Closeout: Export Layer (Read-only)

**التاريخ:** 2026-04-12  
**النتيجة:** PASS — `docker exec saas_app php artisan test tests/Feature/Reporting/`

---

## 1) ملخص تنفيذي

أُضيفت طبقة **تصدير read-only** لتقارير WAVE 2 الموحّدة فقط (`/api/v1/reporting/v1/...`) بصيغ **`csv`**, **`xlsx`** (OpenSpout), **`pdf`** (DomPDF)، مع **بوابة تشغيل** عبر الإعدادات (`REPORTING_EXPORT_ENABLED`)، و**نفس Form Requests والصلاحيات** المستخدمة في واجهات JSON. التصدير يعيد استخدام **نفس الـ Reporters** (لا منطق تقرير مكرّر في الـ controller). **لا mutations** ولا تغيير على النواة المالية. مسار الـ **global feed** يطبّق **حد صفوف** (`reporting.export.max_rows`) مع **`meta.export_*`** عند التصدير.

---

## 2) الملفات

| الملف |
|-------|
| `backend/config/reporting.php` (`export.*` موسّع) |
| `backend/app/Reporting/ReportingApiEnvelope.php` (`report.export.formats_supported`) |
| `backend/app/Reporting/Export/ReportingExportGate.php` |
| `backend/app/Reporting/Export/ReportingEnvelopeTabularConverter.php` |
| `backend/app/Reporting/Export/ReportingExportStreamResponse.php` |
| `backend/app/Http/Controllers/Api/V1/Reporting/ReportingExportController.php` |
| `backend/resources/views/reporting/export_table.blade.php` |
| `backend/routes/api.php` (مسارات `*/export`) |
| `backend/app/Services/Reporting/GlobalOperationsFeedReporter.php` (معامل اختياري لتصدير الـ feed) |
| `backend/tests/Feature/Reporting/ReportingWave2ExportTest.php` |
| `backend/composer.json` / `composer.lock` (`openspout/openspout`) |

---

## 3) migrations

**لا يوجد.**

---

## 4) المسارات (query: `format=csv|xlsx|pdf`)

| التصدير | GET |
|--------|-----|
| Work order summary | `/api/v1/reporting/v1/operations/work-order-summary/export` |
| Company pulse | `/api/v1/reporting/v1/company/pulse-summary/export` |
| Customer pulse | `/api/v1/reporting/v1/customer/pulse-summary/export` |
| Global operations feed | `/api/v1/reporting/v1/operations/global-feed/export` |
| Platform pulse | `/api/v1/reporting/v1/platform/pulse-summary/export` |

**Middleware:** مطابقة مسارات JSON (`reports.view` + `reports.operations.view` حيث ينطبق؛ المنصّة: نفس فحص `SaasPlatformAccess` داخل الـ controller).

---

## 5) الإعدادات

| متغير / مفتاح | المعنى |
|----------------|--------|
| `REPORTING_EXPORT_ENABLED` | تشغيل التصدير (افتراضياً `false` → **404** على مسارات التصدير) |
| `REPORTING_EXPORT_FORMATS` | قائمة مفصولة بفواصل، مثلاً `csv,xlsx,pdf` |
| `REPORTING_EXPORT_MAX_ROWS` | حد جلب صفوف لقائمة الـ feed عند التصدير |
| `REPORTING_EXPORT_PDF_MAX_ROWS` | حد صفوف جدول PDF |

---

## 6) الاختبارات

`ReportingWave2ExportTest`: تعطيل التصدير → 404؛ تمكين CSV لملخص أوامر العمل؛ صيغة غير مدعومة → 422؛ فني → 403.

---

## 7) PASS / FAIL

**PASS** — 40 tests تحت `tests/Feature/Reporting/`.

---

## 8) المخاطر

- **PDF**: جداول كبيرة تُقتطع حسب `pdf_max_rows` (الذاكرة).  
- **تعقيد البيانات**: التحويل إلى جدول مسطح قد يُنتج أعمدة عريضة لـ `data.items`؛ التحليل المعمّق يبقى على JSON API.  
- **التصدير معطّل افتراضياً**: يتطلّب تفعيلاً صريحاً في الإنتاج.

---

## 9) GO / NO-GO لما بعد PR12

**GO** — يمكن توسيع التنسيقات أو تحسين تنسيق الجداول لاحقاً دون تغيير عقود JSON الحالية.
