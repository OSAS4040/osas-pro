# OSAS PRO — دليل النشر

## نظرة عامة

حزمة جاهزة للنشر الإنتاجي (backend بدون `vendor`، وواجهة مبنية في `frontend-dist/`). يُكمّل النشر على الخادم بتوليد المفتاح، التهجير، والكاش.

## المتطلبات

- PHP 8.3+
- PostgreSQL 16+
- Redis 7+
- Node.js غير مطلوب على خادم التطبيق إن وُجدت `frontend-dist/` جاهزة فقط لخدمة الملفات الثابتة.

## خطوات النشر (Backend)

1. رفع محتويات مجلد `backend/` إلى مسار التطبيق (أو استخدام الحزمة كاملة مع الإبقاء على الهيكل).
2. نسخ `deployment/.env.example` إلى `.env` في جذر الـ backend وتعديل القيم (قاعدة البيانات، Redis، إلخ).
3. `composer install --no-dev --optimize-autoloader`
4. `php artisan key:generate` (إن لم يكن `APP_KEY` مضبوطًا)
5. `php artisan migrate --force`
6. `php artisan config:cache`
7. `php artisan route:cache`
8. `php artisan view:cache`
9. ضبط صلاحيات الكتابة على `storage/` و `bootstrap/cache/` (مثلاً `www-data`).
10. تشغيل queue workers حسب إعداداتكم.
11. التحقق من مسار الصحة Laravel: `/up` (أو health API حسب التوجيه).

## خطوات النشر (Frontend)

1. رفع محتويات `frontend-dist/` إلى خادم الويب الثابت (nginx / S3 / CDN) أو خدمة الملفات التي تشير إليها الواجهة.
2. التأكد من أن `VITE_*` أو عنوان الـ API قد بُني في وقت `npm run build` (القيم مُدمجة في البناء).

## التحقق بعد النشر

- تسجيل الدخول
- الشركات
- العملاء
- الفواتير
- التقارير
- التصدير
- واجهات intelligence (حسب التفعيل والصلاحيات)

## ممنوع

- رفع `.env` التطوير أو أي أسرار إلى المستودع.
- تعديل النظام المالي (ledger / journal / wallet) خارج نطاق صيانة مخطط لها.

## Rollback

استرجاع النسخة السابقة من الكود والأصول، إعادة تشغيل الخدمات، وإعادة كاش الإعدادات إن لزم.
