# RELEASE MANIFEST

## الإصدار

v1.0-production-package

## الحالة

READY FOR DEPLOYMENT (بعد تنفيذ `composer install` وتهيئة `.env` على الخادم)

## المحتويات

- `backend/` — Laravel (بدون `vendor`، بدون `node_modules`)
- `frontend-dist/` — مخرجات `npm run build`
- `docs/` — وثائق البوابة والمنصة (الملفات المدرجة فقط)
- `deployment/` — `.env.example` و `docker-compose.yml` (إن وُجد في المصدر)
- `DEPLOYMENT_HANDOVER.md` — دليل النشر

## المستبعدات (عن حزمة التطوير الكاملة)

- `.env` (لا يُرفع مع الحزمة)
- `vendor/` و `node_modules/`
- سجلات `storage/logs/*` ومحتويات `storage/framework/cache/*` (مُفرّغة في النسخة المعبأة)
- مجلدات الاختبارات والتغطية من جذر المشروع (لم تُنسخ ضمن `backend/` حسب نطاق التجميع)

## ملاحظات بناء الواجهة

- تم تشغيل `npm run build` بنجاح؛ ظهرت تحذيرات Rollup لحجم بعض الـ chunks (>500 kB) — ليست أخطاء build.

## ملاحظات تجميع الـ backend

- لم يُنفَّذ `php artisan optimize:clear` على آلة التجميع (PHP غير متوفر في PATH). نفّذوه على CI أو قبل النشر من جذر الـ backend.

## الموجات (مرجعية)

- WAVE 1 / WAVE 2 / WAVE 3 → PR16 / Final Readiness Gate — راجع الملفات في `docs/` المرفقة.
