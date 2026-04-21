# GO / NO-GO وSign-off — إدارة المنصة

## معايير GO

- نجاح النشر الفني (deploy pipeline).
- نجاح `php artisan migrate --force`.
- نجاح تحقق ما بعد النشر (`Platform_Admin_Post_Deploy_Verification.md`).
- نجاح smoke checklist.
- تحقق الصلاحيات للأدوار الخمسة.
- عدم وجود خطأ أمني/تشغيلي حرج مفتوح.

## معايير NO-GO

- فشل migration أو inconsistency في schema.
- فشل مسار حرج في إدارة المنصة.
- ظهور تنفيذ لغير مخول أو تسريب صلاحيات.
- أخطاء 500 متكررة في APIs المنصة.

## المسؤوليات

- **اعتماد النشر:** Platform Owner + Release Owner  
- **تنفيذ النشر:** DevOps / Release Engineer  
- **التحقق بعد النشر:** QA + Operations + ممثل المنصة  
- **قرار الإيقاف أو rollback:** Release Owner بالتنسيق مع Ops

## قائمة الموافقات المطلوبة

- [ ] موافقة Platform Owner
- [ ] موافقة Release Owner
- [ ] موافقة Ops على نافذة التشغيل
- [ ] موافقة QA على نتائج smoke/post-deploy

## موانع الإطلاق

- [ ] أعطال حرجة مفتوحة بدون workaround
- [ ] عدم جاهزية خطة rollback
- [ ] عدم وضوح صلاحيات الإنتاج
- [ ] عدم توفر مالك قرار أثناء نافذة الإطلاق

## نموذج قرار نهائي

- القرار: [GO / NO-GO]
- التاريخ/الوقت:
- المنفذ:
- المعتمد:
- ملاحظات:

