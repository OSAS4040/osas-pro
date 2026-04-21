# خريطة الهجرات والإصدار — إدارة المنصة

## نطاق الهجرات

هذه الخريطة تخص عناصر إدارة المنصة فقط.

## الجداول الجديدة (مباشرة)

- `platform_incidents`
- `platform_incident_lifecycle_events`
- `platform_controlled_actions`
- `platform_decision_log_entries`
- `platform_guided_workflow_idempotency`
- `plan_addons`
- `subscription_addons`
- `platform_audit_logs`
- `platform_announcement_banners`
- `registration_profiles`
- `work_order_cancellation_requests`

## الجداول/الأعمدة المعدلة

- `users`: أعمدة IAM الخاصة بالمنصة (platform role/flags).
- `companies`: أعمدة النموذج المالي (`financial_model`, `financial_model_status`, `credit_limit`, مراجعة المنصة).

## ترتيب التنفيذ الموصى به

1. سحب الإصدار المعتمد.
2. تطبيق الإعدادات (`.env`) المطلوبة للبيئة.
3. تنفيذ:
   - `php artisan migrate --force`
4. (اختياري حسب سياسة البيئة) تشغيل seeders المطلوبة لبيانات التجربة فقط خارج الإنتاج.

## تبعات تشغيلية متوقعة

- زيادة قراءة من جداول منصة الإدارة (خصوصًا الحوادث/الدعم/التنبيهات).
- لا توجد متطلبات تحويل بيانات ماليّة إضافية لهذا المسار.
- لا يوجد تعديل مطلوب على دورات Ledger/Wallet.

## توافقية وتنسيق

- الهجرات additive في معظمها (إضافة جداول/أعمدة).
- يجب التأكد أن إصدار التطبيق وإصدار قاعدة البيانات متزامنان قبل الدخول للمنصة.

## متطلبات config/env

- تفعيل مسارات المنصة وصلاحياتها وفق إعدادات المشروع.
- التأكد من إعدادات مشغل المنصة (platform operator) في IAM.
- لا يوجد متطلب env جديد خاص بالمالية ضمن هذا المسار.

## تحقق بعد `migrate`

- `php artisan migrate:status` يظهر جميع هجرات المنصة بحالة “Ran”.
- لا وجود لخطأ SQL مرتبط بجداول `platform_*`.
- تطبيق المسارات:
  - `/platform/overview`
  - `/platform/intelligence/incidents`
  - `/platform/notifications`
  تعمل لحساب منصة مصرح.

