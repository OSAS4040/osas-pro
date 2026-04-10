## وصف التغيير

<!-- اشرح باختصار ماذا يغيّر هذا الـ PR ولماذا. -->

## نطاق التأثير

<!-- ضع علامة [x] حيث ينطبق -->

- [ ] يمس إعدادات المنصة / SaaS / Platform Admin
- [ ] يمس بوابات النظام (واجهة أو مسارات)
- [ ] يمس ملفات البيئة أو قوالبها (`backend/.env*`, `frontend/env*`)
- [ ] يمس صلاحيات Admin أو منطق المنصة في الخادم
- [ ] لا يمس ما سبق (تغيير عام / ميزة أخرى)

## قائمة تحقق إلزامية (أسس برو)

**المرجع:** [Execution_Order_Asas_Pro.md](docs/Execution_Order_Asas_Pro.md) (الترتيب الرسمي) · [Staging_Gate_Mandatory_Policy.md](docs/Staging_Gate_Mandatory_Policy.md) · [Staging_Governance_Policy.md](docs/Staging_Governance_Policy.md) · [Branch_Protection_And_Review_Policy.md](docs/Branch_Protection_And_Review_Policy.md)

### قبل طلب المراجعة

- [ ] شغّلت **`make policy-env-example`** بنجاح
- [ ] شغّلت **`make staging-gate`** بنجاح (يتطلب `docker compose up -d` حسب الدليل)
- [ ] إن كان الـ PR **خارج** مسارات تشغيل CI تلقائياً: أكّدت التحقق محلياً (البوابة الإلزامية لا تُستبدل بالواجهة فقط)
- [ ] على **`main`**: يجب أن ينجح **`Policy env on PR / policy-env-example`** على GitHub (يُشغَّل تلقائياً على كل PR)؛ **`Staging gate / staging-gate`** عند تطابق المسارات أو التشغيل المحلي — انظر [حماية الفرع](docs/GitHub_Branch_Protection_Setup.md) (لا push مباشر إلى `main`؛ لمسؤولي المستودع بعد ضبط الحماية: **`make github-branch-protection-status`**)

### سياسة بيئة المنصة (لا تُخفّف في الإنتاج)

- [ ] لم أضع **`SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=true`** في قوالب المستودع أو كتوصية إنتاج
- [ ] أي توسيع لـ **`SAAS_PLATFORM_ADMIN_EMAILS`** مبرّر تشغيلياً ومذكور في الوصف

### بعد الدمج / قبل الإنتاج

- [ ] أدرك أن نقل إعدادات Staging/SaaS/البوابات للإنتاج يتطلب **الفحص اليدوي المختصر** وثبات الخدمات (انظر [Staging_Deploy_Runbook.md](docs/Staging_Deploy_Runbook.md))

---

**تذكير:** مرجع الحماية هو **الخادم ونتائج البوابات**، وليس إخفاء عناصر الواجهة وحدها.

## روابط مرتبطة (اختياري)

<!-- Issues، وثائق، لقطات -->
