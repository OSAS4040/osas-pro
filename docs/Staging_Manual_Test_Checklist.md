# قائمة اختبار Staging — يدوي (بالترتيب الموصى به)

**الترتيب التشغيلي + قرار PASS/FAIL:** [`Staging_Execution_Now.md`](./Staging_Execution_Now.md).

**المرجع:** [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md) · [`Execution_Order_Asas_Pro.md`](./Execution_Order_Asas_Pro.md) (المرحلة 3).

**المرحلة التالية (Pilot — آمنة):** [`Pilot_Phase_Safe_Next_Steps.md`](./Pilot_Phase_Safe_Next_Steps.md) · قراءة فقط: `scripts/preflight-pilot-readonly.ps1` · أتمتة خطوة 2 على Windows: `scripts/pilot-step2-docker.ps1`.

استخدم هذه القائمة **بعد** نشر خادم + واجهة Staging، و**مع** نفس إصدار الكود الذي تختبرونه.

---

## أ) أتمتة (قبل أو بعد إقلاع Staging حسب عادتكم)

| # | الإجراء | ملاحظة |
|---|---------|--------|
| A1 | `make policy-env-example` أو `node scripts/check-policy-env-example.mjs` | لا يحتاج Docker؛ يتحقق من قوالب env في المستودع |
| A2 | `docker compose up -d` ثم `make staging-gate` (أو على Windows: `scripts/pilot-step2-docker.ps1` لخطوة 2 كاملة مع `verify` و`integrity`) | Vitest + PHPUnit مسار المنصة/SaaS (+ بوابة أوسع إن استخدمت السكربت) |
| A2b | (اختياري، محلي) `scripts/pilot-step3-local-gate.ps1` و`-WithE2e` لـ `test:ci` | **A1** + `health` + جذر nginx (تحذير 502 متوقع بدون SPA) + Playwright كما في CI |
| A3 | (اختياري) نجاح **`Policy env on PR`** على GitHub عند وجود PR | يطابق سياسة القوالب |

**ترتيب عملي:** يمكن تشغيل **A1** في أي وقت؛ **A2** بعد توفر Docker و`.env` محلي يطابق التطوير.

---

## ب) بيئة Staging جاهزة للمستخدم

| # | الإجراء | يُعتبر ناجحاً عندما |
|---|---------|---------------------|
| B1 | فتح URL الواجهة على Staging | تحميل بدون خطأ شبكة عام |
| B2 | تسجيل دخول **staff** (مالك/موظف تجريبي) | الدخول إلى لوحة التطبيق الرئيسية |
| B3 | زيارة مسارات أساسية: الرئيسية، إعدادات (إن لديكم صلاحية)، فاتورة/قائمة مختصرة | لا شاشة بيضاء؛ لا 404 غير متوقع للمسارات المفعّلة |

---

## ج) صلاحيات المنصة (الخادم هو المرجع)

**استبدل** `BASE` و`TOKEN` و`TOKEN_DENIED` بالقيم الفعلية.

| # | الإجراء | متوقع |
|---|---------|--------|
| C1 | مستخدم بريده في `SAAS_PLATFORM_ADMIN_EMAILS` — طلب `GET {BASE}/api/v1/admin/companies` مع `Authorization: Bearer {TOKEN}` | **HTTP 200** وجسم يحتوي `data` |
| C2 | مستخدم **ليس** في القائمة — نفس الطلب بـ `TOKEN_DENIED` | **HTTP 403** ويفضّل وجود `code` مثل `PLATFORM_OPERATOR_REQUIRED` |

مثال (تعديل القيم):

```bash
curl -sS -o /dev/null -w "%{http_code}" -H "Authorization: Bearer TOKEN" -H "Accept: application/json" "https://staging.example.com/api/v1/admin/companies"
```

---

## د) بوابات الواجهة (`VITE_ENABLED_PORTALS`)

| # | الإجراء | متوقع |
|---|---------|--------|
| D1 | إن عُطّلت بوابة (مثلاً بدون `admin` في القائمة البيضاء): محاولة فتح `/admin` كـ staff | إعادة توجيه إلى لوحة التطبيق أو مسار آمن — **لا** شاشة بيضاء |
| D2 | مستخدم **Fleet** وبوابة الأسطول معطّلة في البناء | إعادة إلى `/login` مع إشعار أو سلوك موثّق في المنتج |
| D3 | مستخدم **Customer** وبوابة العملاء معطّلة | مثل D2 |

---

## هـ) بعد النشر — مراقبة سريعة (~5 دقائق)

| # | ماذا تراقب |
|---|------------|
| H1 | **Logs** التطبيق (Laravel / nginx / حاوية `app`) — أخطاء PHP، مهلة DB |
| H2 | **Queues** — طوابير فاشلة، jobs عالقة |
| H3 | **4xx/5xx** — معدل أخطاء غير معتاد، خاصة 500 على `/api/v1/health` والمسارات الحرجة |

---

## خاتمة

- **نجاح القائمة** لا يغني عن **`make verify`** أو **`release-gate`** قبل إصدار حرج إن كانت سياسة الفريق تتطلب ذلك.
- أي فشل في **ج** أو **د** يُعالَج على **Staging** قبل نقل نفس الإعدادات إلى **الإنتاج**.
