# اعتماد بوابة Staging كشرط إلزامي قبل أي تغيير إنتاجي (أسس برو)

**ترتيب التنفيذ المرقم:** [`Execution_Order_Asas_Pro.md`](./Execution_Order_Asas_Pro.md).

**الحالة:** معتمدة للفريق التقني.  
**مرتبطة بـ:** [`Staging_Governance_Policy.md`](./Staging_Governance_Policy.md) · [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md) · [`Branch_Protection_And_Review_Policy.md`](./Branch_Protection_And_Review_Policy.md).

---

## 1) الاعتماد الرسمي

يُعتمد ما يلي كجزء **إلزامي** من دورة التسليم قبل أي تغيير على **الإنتاج**:

| المكوّن | الوصف |
|---------|--------|
| [`scripts/staging-gate.sh`](../scripts/staging-gate.sh) | سكربت موحّد (Vitest + PHPUnit مسار المنصة/SaaS) |
| `make staging-gate` | نفس المنطق عبر Makefile |
| `make policy-env-example` | تحقق ثابت من سياسة أمثلة الإعداد |
| [`.github/workflows/staging-gate.yml`](../.github/workflows/staging-gate.yml) | CI على PR (حسب المسارات المعتمدة في الملف) |

---

## 2) شرط ما قبل الدمج

لا يُعتمد أي **PR** يمس أياً مما يلي **إلا بعد** نجاح **`policy-env-example`** و**`staging-gate`** (محلياً أو عبر GitHub Actions بحسب المسار):

- إعدادات المنصة
- بوابات النظام
- صلاحيات Admin / Platform
- ملفات البيئة (أو قوالبها في المستودع)
- منطق SaaS

**ملاحظة تشغيلية:** إذا وقع التغيير **خارج** مسارات تشغيل الـ workflow تلقائياً، يبقى **الالتزام بالتشغيل المحلي** (`make policy-env-example` و`make staging-gate`) على عاتق مُقدِّم الطلب حتى يُدمَج.

---

## 3) شرط ما قبل الإنتاج

يُمنع نقل أي إعدادات أو تغييرات تخص **Staging**، **Platform Admin**، **سياسة SaaS**، **البوابات**، أو **ملفات env** إلى **الإنتاج** قبل تحقق ما يلي:

1. نجاح **بوابة Staging** (نفس المعايير أعلاه).
2. **الفحص اليدوي المختصر** من [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md).
3. **ثبات الصحة العامة** للخدمات بعد النشر على البيئة المعنية.

---

## 4) الضوابط الإلزامية

| يُمنع |
|--------|
| تفعيل **`SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=true`** في الإنتاج أو في قوالب المستودع |
| توسيع **`SAAS_PLATFORM_ADMIN_EMAILS`** دون حاجة تشغيلية معتمدة |
| **تجاوز** بوابة Staging لأي تغيير يمس صلاحيات المنصة أو بواباتها |

---

## 5) آلية العمل المعتمدة

التسلسل الرسمي:

1. تطوير التعديل.
2. تشغيل: **`make policy-env-example`** ثم **`make staging-gate`** (مع تشغيل Docker حسب الدليل).
3. دفع الفرع وفتح **PR**.
4. نجاح **`staging-gate.yml`** على GitHub عندما يُشغَّل تلقائياً للمسار.
5. اعتماد الدمج.
6. نقل التغيير للإنتاج وفق **نفس القواعد المحافظة** (انظر [`Staging_Governance_Policy.md`](./Staging_Governance_Policy.md)).

**قالب طلب الدمج:** عند فتح PR على GitHub يُعرض تلقائياً [`.github/PULL_REQUEST_TEMPLATE.md`](../.github/PULL_REQUEST_TEMPLATE.md) كقائمة تحقق.

---

## 6) مرجع الحماية

- **صلاحيات الخادم** و**نتائج بوابة Staging** هي المرجع المعتمد.
- **ظهور أو إخفاء الواجهة** وحده **لا يكفي** كدليل حماية.

---

## 7) الهدف

- تثبيت الأمان التشغيلي.
- منع التغييرات غير المنضبطة على الإنتاج.
- توحيد مسار التحقق.
- رفع الثقة في أي نشر لاحق.
