# تسليم نشر — قالب لفريق النشر

**الغرض:** مستند قصير يُرفق مع طلب النشر (Staging / Production) ويُحدّد ما يُنفَّذ وما يُتحقق منه بعد النشر.

---

## 1. بيانات الطلب

| الحقل | القيمة |
|--------|--------|
| تاريخ الطلب | _YYYY-MM-DD_ |
| البيئة المستهدفة | _Staging / Production_ |
| المرجع (فرع / وسم / بناء) | _مثال: `main` @ commit أو رقم البناء_ |
| طالب النشر | _الاسم / الفريق_ |
| نافذة النشر المفضّلة (اختياري) | _من — إلى بتوقيت محلي_ |

---

## 2. نطاق النشر (مختصر)

- [ ] **Backend** (Laravel — ترحيلات / تكوين)
- [ ] **Frontend** (بناء Vite — `npm run build`)
- [ ] **Docker / Compose** (تغيير في الصور أو التعريفات)
- [ ] **متغيرات بيئة** (`.env` / أسرار — اذكر المفاتيح دون القيم الحساسة)
- [ ] **أخرى:** _وصف سطر واحد_

**ملاحظات للنشر (اختياري):**  
_مثال: لا تشغيل `migrate` على الإنتاج قبل موافقة DBA؛ أو يتطلب إعادة بناء الواجهة فقط._

---

## 3. مراجع تنفيذية (داخل المستودع)

| المستند | الاستخدام |
|---------|-----------|
| [`deployment/official_release_package/README_DEPLOYMENT.md`](../deployment/official_release_package/README_DEPLOYMENT.md) | نظرة عامة على الحزمة والمتطلبات |
| [`deployment/official_release_package/DEPLOYMENT_STEPS.md`](../deployment/official_release_package/DEPLOYMENT_STEPS.md) | خطوات النشر التفصيلية |
| [`deployment/official_release_package/POST_DEPLOY_CHECKLIST.md`](../deployment/official_release_package/POST_DEPLOY_CHECKLIST.md) | قائمة ما بعد النشر |
| [`docs/Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md) | سياسة Staging أولاً والفحص السريع |

---

## 4. تحقق سريع بعد النشر (يُعلّم منفّذ النشر)

- [ ] التطبيق يستجيب (HTTP 200 على المسار الرئيسي أو health إن وُجد).
- [ ] `APP_ENV` / `APP_DEBUG` متوافقة مع البيئة (إنتاج: `production` و `false`).
- [ ] تم تشغيل الترحيلات إن طُلب ذلك، وبدون أخطاء ظاهرة في السجل.
- [ ] الواجهة تعرض الإصدار المتوقع (كاش CDN/متصفح إن لزم).

**نتيجة الفحص:** _PASS / FAIL — ملاحظة سطر واحد_

---

## 5. Rollback (اختياري)

**في حال الفشل:** _مثال: إعادة وسم الصورة السابقة / استرجاع commit السابق / تعطيل الميزة عبر feature flag._

---

*هذا القالب لا يستبدل سياسات الحوكمة أو خطوات الأمان المعتمدة في المؤسسة؛ يكمّلها كملخص عملياتي لطلب واحد.*
