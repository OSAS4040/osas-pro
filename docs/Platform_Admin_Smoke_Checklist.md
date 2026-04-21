# Smoke Checklist — إدارة المنصة

- [ ] النشر اكتمل بدون أخطاء حرجة.
- [ ] `php artisan migrate --force` نجح.
- [ ] فتح `/platform/overview` ناجح.
- [ ] فتح `/platform/intelligence/incidents` ناجح (Incident Center).
- [ ] فتح `/platform/intelligence/incidents/:incidentKey` ناجح (Incident Detail).
- [ ] قراءة Decision Log داخل Incident Detail ناجحة.
- [ ] تنفيذ Guided Workflow آمن ناجح (صلاحية صحيحة + استجابة صحيحة).
- [ ] فتح `/platform/intelligence/command` ناجح (Command Surface).
- [ ] فتح Controlled Actions داخل Incident Detail ناجح.
- [ ] فتح `/platform/notifications` ناجح (جميع التنبيهات).
- [ ] الجرس في هيدر المنصة يظهر العدّاد والقائمة المختصرة.
- [ ] التحقق من role visibility للأدوار الخمسة ناجح.
- [ ] لا أخطاء تحميل/صلاحيات واضحة في الواجهة.

**نتيجة نهائية:**  
- [ ] PASS  
- [ ] FAIL (ابدأ rollback)

