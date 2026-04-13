# قائمة التحقق بعد النشر

استخدم هذه القائمة كـ **gate** قبل إعلان الإطلاق.

## إعدادات Laravel

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL` يطابق النطاق والمخطط (https)
- [ ] `APP_KEY` مُولَّد وغير فارغ
- [ ] `APP_PUBLIC_URL` / `FRONTEND_PUBLIC_URL` متسقة مع الواجهة العامة

## قاعدة البيانات

- [ ] اتصال PostgreSQL يعمل من خادم التطبيق
- [ ] `php artisan migrate --force` اكتمل بدون أخطاء
- [ ] نسخ احتياطي تلقائي للقاعدة مُخطط له (خارج نطاق هذا الملف)

## Redis / الطوابير

- [ ] Redis يقبل الاتصال (جلسة، كاش، طابور)
- [ ] عمال الطابور يعملون (`queue:work` أو حاويات queue_*)
- [ ] `schedule:run` في crontab إذا استُخدمت المهام المجدولة

## الملفات والصلاحيات

- [ ] `backend/storage` و`backend/bootstrap/cache` قابلان للكتابة لمستخدم PHP
- [ ] `php artisan storage:link` إن لزم الوصول للملفات العامة
- [ ] لا توجد ملفات `.env` في مستودع عام أو في حزمة مُصدَّرة بالخطأ

## التبعيات والبناء

- [ ] `composer install --no-dev` (أو ما يعادله في Docker) على الإنتاج
- [ ] `npm ci` و `npm run build` للواجهة ونشر `dist/`
- [ ] إزالة أدوات التطوير من مسار الإنتاج إن لزم

## تحسين الأداء

- [ ] `php artisan optimize:clear` ثم:
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache` (إن لم يسبب أخطاء)
- [ ] `php artisan view:cache` (إن لم يسبب أخطاء)

## التحقق الوظيفي

- [ ] الصفحة الرئيسية للواجهة تحمّل بدون أخطاء شبكة حرجة
- [ ] تسجيل الدخول يعمل
- [ ] `GET /api/v1/health` يعيد `healthy` (أو degraded مع تفسير مقبول)
- [ ] أصل ثابت رئيسي (CSS/JS) يحمّل بـ 200
- [ ] مراجعة `storage/logs/laravel.log` بعد الإقلاع — لا أخطاء حرجة متكررة

## الأمان

- [ ] HTTPS مفعّل
- [ ] لا مفاتيح API أو كلمات مرور في السجلات
- [ ] Sentry/webhooks مضبوطة فقط إذا كانت سياسة الأمان تسمح

## الحكم

سجّل التاريخ والمسؤول عند اكتمال القائمة.
