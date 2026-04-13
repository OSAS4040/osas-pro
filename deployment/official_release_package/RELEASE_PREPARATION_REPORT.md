# تقرير تجهيز حزمة النشر

**تاريخ التوليد:** 2026-04-10  
**مسار الحزمة:** `deployment/official_release_package/`  
**الأصل:** نسخ (copy) من جذر المشروع — **لم يُحذف ولم يُنقل ولم يُعدَّل أي ملف في المشروع الأصلي** أثناء إنشاء الحزمة.

---

## ما تم تضمينه

- `backend/` — كود Laravel كاملاً **دون** `vendor/`، **دون** `tests/`، **دون** `tools/dev/`.
- `frontend/` — كود المصدر **دون** `node_modules/` و**دون** `dist/` (يُبنى عند النشر).
- `docker/` — إعدادات Nginx/PHP المرجعية.
- `docker-compose.yml`, `Makefile`, `README.md` من جذر المشروع.
- `docs/` — **دون** `docs/node_modules/`.
- `osas/docker-compose.yml`.
- ملفات توثيق النشر: `README_DEPLOYMENT.md`, `DEPLOYMENT_STEPS.md`, `ENVIRONMENT_VARIABLES_REQUIRED.md`, `POST_DEPLOY_CHECKLIST.md`, `DIRECTORY_STRUCTURE.md`, `.env.example` (جذر الحزمة).

---

## ما تم استبعاده من الحزمة (وليس حذفاً من الأصل)

| البند | السبب |
|--------|--------|
| `backend/vendor/` | يُعاد إنشاؤه بـ `composer install`. |
| `frontend/node_modules/` | يُعاد إنشاؤه بـ `npm ci`. |
| `backend/tests/` | غير مطلوبة لتشغيل الإنتاج. |
| `backend/tools/dev/` | أدوات تطوير محلية. |
| `docs/node_modules/` | تبعيات وثائقية ثقيلة غير لازمة للتشغيل. |
| `.git/`, `.github/` | لم تُنسخ ضمن مسار الحزمة. |
| `node_modules` في أي مسار | استبعاد عام. |
| ملفات `.env` الحقيقية | مُستبعدة صراحةً من النسخ (قوالب `.env.example` فقط). |
| `load-testing/`, `scripts/performance/` (k6)، `reports/`، `.cursor/` | غير مُضمَّنة في عملية النسخ الأولى لهذه الحزمة. |
| `backend/storage/logs/*.log` | إن وُجدت بعد النسخ — **حُذفت من نسخة الحزمة فقط** لتقليل الضوضاء. |
| `backend/.phpunit.result.cache`, `backend/vendor.zip`, `backend/run-tests.sh`, `backend/.env.testing` | أزيلت **من نسخة الحزمة فقط** كعناصر غير لازمة للنشر. |
| `backend/phpunit.xml` | أزيل **من نسخة الحزمة فقط** (الاختبارات مُستبعدة). |

---

## افتراضات

- النشر على **PostgreSQL** و**Redis** كما في الإعداد الافتراضي للمشروع.
- استخدام **HTTPS** في الإنتاج.
- بناء الواجهة بـ `npm run build` على الخادم أو في CI.

---

## عناصر أُبقيت احتياطياً للمراجعة

- `backend/.env.staging.example` — مثال بيئة، **لا يحتوي أسراراً**؛ يمكن للنشر تجاهله أو دمجه مع سياساتكم.
- `backend/composer.lock` — يُفضّل الإبقاء عليه لتثبيت إصدارات متطابقة.
- مجلد `docs/` قد يحتوي ملفات Markdown كثيرة؛ راجعوا ما يُنشر علناً.

---

## Files kept for safety review

العناصر التالية وُجدت في الأصل وقد تبدو غير مألوفة؛ **لم تُحذف من المشروع الأصلي**. في الحزمة:

- تمت إزالة `backend/composer.jsoncd` من **نسخة الحزمة فقط** إن وُجد (ملف غير قياسي؛ يستحق مراجعة في المصدر دون حذف إلزامي من الأصل).

---

## نقاط تتطلب انتباهاً قبل التسليم

1. التأكد من عدم وجود **أسرار** في أي ملف داخل الحزمة (بحث عن أنماط مفاتيح).
2. مراجعة سياسة شركة النشر: هل تريد **تضمين `frontend/dist/`** جاهزاً أم البناء على الخادم؟
3. التحقق من توافق إصدار **PHP** (8.2+، Dockerfile يستخدم 8.3).

---

## الحكم النهائي

**READY FOR DEPLOYMENT PACKAGE REVIEW**

الحزمة جاهزة لمراجعة فريق النشر والأمن والتشغيل، مع افتراض إكمال `composer install` و`npm run build` وضبط `backend/.env` على البيئة المستهدفة.

---

## تأكيد عدم المساس بالمصدر الأصلي

تم إنشاء المجلد `deployment/official_release_package/` ونسخ الملفات إليه فقط. **لم يُجرَ أي حذف أو تعديل أو نقل داخل شجرة المشروع الأصلي** كجزء من هذه المهمة.
