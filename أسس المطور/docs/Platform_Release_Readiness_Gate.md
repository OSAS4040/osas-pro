# Platform Release Readiness Gate

بوابة جاهزية للنشر على **Staging** ثم **Production**. تكمّل الوثائق القائمة مثل `Staging_Deploy_Runbook.md` و `Staging_Execution_Now.md` إن وُجدت في `docs/`.

---

## 1. ما قبل الدمج (Pre-merge)

- [ ] PR صغير نسبياً ومحدد المسار (لا "mega PR" للموجة الحساسة)  
- [ ] `Platform_Testing_Gate.md` — متطلبات الموجة مُنجزة  
- [ ] لا migrations خطرة بدون خطة rollback ونسخة احتياطية  
- [ ] مراجعة `Platform_Financial_Control_Policy.md` إن لمس المالية  

---

## 2. Staging — بعد الدمج

- [ ] نشر التطبيق (Docker / pipeline الداخلي) حسب [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md)  
- [ ] `php artisan migrate --force` (أو ما يعادله) **تحت مراقبة**  
- [ ] فحص صحة: `/api/v1/health` (أو المسار المعتمد)  
- [ ] **تدفق دخول** أساسي (موظف + عميل إن انطبق)  
- [ ] **تدفق فاتورة/دفع تجريبي** بدون بيانات إنتاج حقيقية  
- [ ] سجلات: لا أخطاء حرجة في structured logs  

---

## 3. بوابات الجودة المعتمدة محلياً

| البوابة | الوصف |
|---------|--------|
| `scripts/staging-gate.sh` | Vitest + PHPUnit للمسارات المعتمدة |
| `scripts/preflight-pilot-readonly.ps1` | فحوصات قراءة فقط ضد عنوان Staging |
| `npm run test:ci` (frontend) | lint + types + tests + e2e |

سجّل: التاريخ، الفرع، نتيجة PASS/FAIL، من نفّذ.

---

## 4. Production — إضافي

- [ ] نسخ احتياطي لقاعدة البيانات  
- [ ] نافذة صيانة إن لزم  
- [ ] مراقبة لأول 30–60 دقيقة (أخطاء 5xx، طوابير jobs)  
- [ ] خطة rollback (إعادة صورة + تراجع migrations إن أمكن)  

---

## 5. معايير NO-GO للنشر

- فشل أي اختبار مالي تكاملي حرج  
- كسر علاقات FK أو بيانات شبه منعدمة في جداول المحفظة/القيود  
- غموض في الصلاحيات (تسرّب owner actions لدور أدنى)  
- migrations غير قابلة للتراجع بدون خطة  

---

## 6. سجل الإصدار (قالب)

| الحقل | القيمة |
|--------|--------|
| الإصدار / الـ commit | |
| البيئة | staging \| production |
| منفّذ النشر | |
| نتيجة البوابات | PASS / FAIL |
| ملاحظات | |

---

*WAVE 0 — إنشاء القالب؛ الاستخدام الفعلي عند كل نشر.*
