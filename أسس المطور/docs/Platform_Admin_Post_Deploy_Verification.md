# التحقق بعد النشر — إدارة المنصة

## الهدف

تأكيد أن إدارة المنصة تعمل وظيفيًا وصلاحيًا بعد النشر.

## خطوات التحقق (مختصرة وواضحة)

1. تسجيل دخول حساب `platform_admin`.
2. فتح `/platform/overview` والتأكد من تحميل الصفحة بدون أخطاء.
3. فتح:
   - `/platform/intelligence/incidents`
   - `/platform/intelligence/incidents/:incidentKey`
   - `/platform/intelligence/command`
   - `/platform/notifications`
4. التحقق من:
   - Incident Center
   - Incident Detail
   - Decision Log داخل Incident Detail
   - Guided Workflows داخل Incident Detail
   - Controlled Actions داخل Incident Detail
   - Command Surface
   - Center + Bell + All Notifications

## تحقق الصلاحيات الأساسية

اختبار أدوار:
- `platform_admin`
- `operations_admin`
- `support_agent`
- `auditor`
- `finance_admin`

التحقق:
- ظهور/اختفاء العناصر حسب الصلاحية.
- عدم فتح صفحات حساسة بدون إذن.
- ظهور رسالة واضحة عند الرفض: **ليس لديك صلاحية للوصول**.

## PASS / FAIL

### PASS
- كل الصفحات الحرجة تفتح وتحمّل بنجاح.
- الروابط العميقة تعمل (Deep Links).
- الصلاحيات تمنع/تسمح بشكل صحيح.
- لا أخطاء Console/API حرجة.

### FAIL
- فشل تحميل صفحة حرجة.
- كسر صلاحيات (عرض بيانات/زر تنفيذ لغير مصرح).
- فشل مسار Incident أو Decision أو Workflows أو Controlled Actions.
- فشل مركز التنبيهات أو Deep Link.

عند FAIL: إيقاف الإطلاق والانتقال إلى `Platform_Admin_Rollback_Plan.md`.

