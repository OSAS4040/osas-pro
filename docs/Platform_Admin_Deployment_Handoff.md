# تجهيز حزمة تسليم النشر الرسمية — إدارة المنصة

## ابدأ من هنا

هذا الملف هو نقطة البداية لفريق النشر.  
الهدف: نشر تحديثات إدارة المنصة والتحقق منها وتشغيل خطة الرجوع عند الحاجة، بدون أي تغييرات وظيفية خارج النطاق.

## ملخص تنفيذي (قصير)

- الإصدار يشمل تثبيت مسارات إدارة المنصة (`/platform/*`) وتحديثات ذكاء المنصة.
- إضافة طبقة **مركز التنبيهات والمتابعة** (قراءة/تنبيه وروابط مباشرة فقط).
- لا يوجد إدخال مزايا مالية جديدة، ولا تعديل منطق `Ledger / Wallet / Posting / Reconciliation`.

## ما الذي تغيّر في هذا الإصدار

- Backend:
  - APIs إدارة المنصة والصلاحيات المرتبطة.
  - مسار جديد للتنبيهات: `GET /api/v1/platform/notifications`.
  - جداول/كيانات ذكاء المنصة (حوادث، قرارات، إجراءات مضبوطة، idempotency workflows).
- Frontend:
  - مسارات منصة إضافية (ومنها: `platform-notifications`).
  - قسم رئيسي: **يحتاج انتباهك الآن**.
  - جرس تنبيهات في هيدر إدارة المنصة.
  - صفحة **جميع التنبيهات**.

## ما الذي لا يشمله هذا الإصدار

- لا إدخال remediation أو أوامر مالية تشغيلية جديدة.
- لا تغيير Domain-sensitive في مسارات مالية.
- لا إعادة تصميم معماري خارج نطاق منصة الإدارة.

## ترتيب تنفيذ النشر

1. مراجعة قرار الإطلاق في `Platform_Admin_Go_No_Go_And_Signoff.md`.
2. مراجعة خريطة الهجرات في `Platform_Admin_Migration_And_Release_Map.md`.
3. تنفيذ خطوات النشر (build/deploy) المعتمدة داخليًا.
4. تشغيل الهجرات: `php artisan migrate --force`.
5. تنفيذ تحقق ما بعد النشر من `Platform_Admin_Post_Deploy_Verification.md`.
6. تنفيذ قائمة smoke السريعة من `Platform_Admin_Smoke_Checklist.md`.
7. في حال فشل بوابة GO، الانتقال مباشرة إلى `Platform_Admin_Rollback_Plan.md`.

## خطوات حرجة (تنبيه واضح)

- ممنوع بدء النشر بدون نافذة رجوع واضحة (rollback window).
- ممنوع تنفيذ rollback جزئي غير موثق.
- لا اعتماد النشر قبل اجتياز تحقق الصلاحيات والمسارات الحرجة.
- أي فشل في وصول مشغّل المنصة لمسارات `/platform/*` يعتبر مانع GO.

## الملفات المرجعية في الحزمة

- `Platform_Admin_Migration_And_Release_Map.md`
- `Platform_Admin_Post_Deploy_Verification.md`
- `Platform_Admin_Rollback_Plan.md`
- `Platform_Admin_Smoke_Checklist.md`
- `Platform_Admin_Route_And_Permission_Map.md`
- `Platform_Admin_Operational_Runbook.md`
- `Platform_Admin_Go_No_Go_And_Signoff.md`

## مخرجات الإغلاق المطلوبة من فريق النشر

- نتيجة migrate.
- نتيجة smoke.
- نتيجة تحقق الصلاحيات.
- قرار GO/NO-GO موقّع.
- حالة نهائية: Release Passed أو Rolled Back.

