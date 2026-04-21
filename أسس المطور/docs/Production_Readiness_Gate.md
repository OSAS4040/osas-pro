# Production Readiness Gate — بوابة جاهزية مُقيَّدة بالشروط (Condition-Based)

المرجع التشغيلي لـ **تثبيت النطاق** و**منع التوسع** حتى يثبت الاستقرار **فعلياً** (بدون الالتزام بعدد أيام ثابت).

---

## 1) النطاق — ثابت، لا تعديل

```text
Workshop + Fleet + Invoices + Wallet + Inventory
```

| مسموح | ممنوع |
|--------|--------|
| إصلاحات ضمن هذا النطاق فقط، وفق بوابة المرور أدناه | إضافة **ميزات** خارج النطاق |
| | تعديل **منطق مالي أو مخزون** «على الهامش» أو بدون بوابة |

---

## 2) أوامر التشغيل بعد كل نشر (Staging)

**بالترتيب؛ أي خطوة تفشل = النشر غير مقبول.**

```bash
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan integrity:verify
FAIL_ON_FAILED_JOBS=1 REDIS_PASSWORD=... CHECK_BASE_URL=... ./check.sh
docker compose exec -T app php artisan test --group=pre-production
```

> على **staging الرسمي** استبدل `...` بقيم البيئة الفعلية (Redis، عنوان الوصول لـ `/api/v1/health`, …). على Windows نفِّذ `./check.sh` من Git Bash أو WSL إن لزم.

---

## 3) اختبار واقعي — إجباري (يدوي أو `@group pre-production`)

**السيناريو:** Customer → Vehicle → Work Order → Service (أو بند خدمة) → Invoice → Payment  

**تحقق صريح:**

- الفاتورة أُنشئت.
- المخزون خُصم (للبنود المخزنية).
- الدفع سُجّل ولا تكرار مالي لنفس الإيدمبوتنسي.
- القيد المحاسبي موجود ومتوازن للفاتورة (حيث يُطبّق الترحيل).
- لا تكرار فاتورة لنفس مصدر أمر العمل.

**أتمتة مرجعية في المستودع:** `docker compose exec -T app php artisan test --group=pre-production`

---

## 4) سجل نتائج البوابة (يُحدَّث بعد كل نشر)

**مبدأ:** كل صف يجب أن يذكر **البيئة** صراحةً (`محلي` / `staging رسمي` / `CI`).

| Date       | Result | Notes |
|------------|--------|-------|
| 2026-04-01 | PASS | **بيئة:** `docker compose` لهذا المستودع (Docker Desktop) — يعادل «staging مكدس المشروع» إن لم يكن لديكم مضيف منفصل. **الدورة:** `migrate` OK، `integrity:verify` OK، `check.sh` + `FAIL_ON_FAILED_JOBS=1` (`CHECK_BASE_URL=http://localhost`، `REDIS_PASSWORD` حسب compose المحلي) OK، `pre-production` 3/3 (47 assertion). `GET /api/v1/system/version` → 404 (غير مانع لـ `check.sh`). **قيود الصدق:** لم يُنفَّذ تحقق SSH على سيرفر staging شركة؛ إن كان المعتمد عندكم **مضيف نشر آخر** فأضف صفاً لاحقاً باسم ذلك المضيف بعد نفس التسلسل. |
| 2026-04-02 | PASS | env=local; host=local-compose; runner=cursor-agent; notes=run-staging-gate.ps1 completed (migrate/integrity/strict-gate/pre-production all PASS); log=N/A |

**قالب صف — انسخه إلى الجدول بعد كل تشغيل:**

```md
<!-- Template: Copy this row after each run -->
| YYYY-MM-DD | PASS/FAIL | env=staging; host=<HOST/URL>; runner=<RUNNER>; notes=<short>; log=<failure.log|N/A> |
```

### قواعد استخدام القالب

- استبدل `YYYY-MM-DD` بتاريخ التنفيذ.
- استبدل `PASS/FAIL` بالنتيجة الفعلية (`PASS` أو `FAIL`).
- **`host`:** اسم الخادم أو الـ URL المعتمد لـ staging.
- **`runner`:** إن وُجد (GitHub Actions، GitLab Runner، أو اسم الجهاز المنفِّذ).
- **`notes`:** ملاحظة قصيرة (طوابير، زمن، migrations، إلخ).
- **`log`:** عند الفشل مرّر مرجع **`failure.log`** (أو مسار artifact)؛ عند النجاح **`N/A`**.

**مثال PASS:**

```md
| 2026-04-02 | PASS | env=staging; host=staging.osas.sa; runner=gitlab-runner-1; notes=stable; log=N/A |
```

**مثال FAIL:**

```md
| 2026-04-03 | FAIL | env=staging; host=staging.osas.sa; runner=gitlab-runner-1; notes=queue backlog; log=failure.log |
```

**هدف التوحيد:** كل تشغيل قابل للتتبع، كل فشل له دليل (artifact)، ولا لبس بين **local** و **staging**.

**قاعدة:** أي تشغيل لبوابة §2 **بدون** إضافة صف في الجدول أعلاه = **غير معتمد** كنشر أو كنقطة مرور رسمية.

**الخطوة التنفيذية التالية (إلزامية قبل أي «مرحلة تالية»):** نفِّذ نفس دورة §2 على **staging الرسمي** بقيم البيئة الفعلية هناك، ثم أضف **صفاً جديداً** بالقالب (مثال PASS: `env=staging; host=<HOST/URL>; runner=<RUNNER>; notes=…; log=N/A` — أو FAIL مع `log=failure.log`). لا يُعتبر الانتقال تشغيلياً مكتملاً بدون **PASS موثّق على ذلك المضيف** (تشغيل محلي/compose وحده لا يغني عنه إن كان staging عندكم خادماً منفصلاً).

**ملاحظات مفتوحة (لا توقف البوابة حالياً):**

- `GET /api/v1/system/version` قد يعيد **404** في بعض البيئات؛ **غير مانع** لـ `check.sh` في وضعه الحالي. **سجّل** ذلك في `notes` عند الحاجة. عند الهدف لاحقاً لربط **إثبات الإصدار (release proof)** بشكل كامل بين الواجهة والخادم، يُعالَج البند ويُغلق صراحةً في التوثيق أو في نفس الجدول.

### إرسال النتيجة فقط (للمُنفِّذ على المضيف المعتمد)

**الأوامر:**

```bash
docker compose up -d
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan integrity:verify
FAIL_ON_FAILED_JOBS=1 REDIS_PASSWORD=... CHECK_BASE_URL=... ./check.sh
docker compose exec -T app php artisan test --group=pre-production
```

**عند الفشل:**

```bash
docker compose logs > failure.log
```

**أرسلوا النتيجة فقط** (بدون أي تعديل على الصيغة).

**PASS**

```text
date: YYYY-MM-DD
result: PASS
host: <ACTUAL_HOST_OR_URL>
runner: <ACTUAL_RUNNER>
notes: stable; system/version=404 (إن وُجد)
log: N/A
```

**FAIL**

```text
date: YYYY-MM-DD
result: FAIL
host: <ACTUAL_HOST_OR_URL>
runner: <ACTUAL_RUNNER>
notes: <SHORT_REASON>
log: failure.log
```

**قواعد:** لا قيم تقديرية — لا تعديل على الصيغة — لا فتح نطاق جديد — أرسلوا النتيجة فور الانتهاء.

---

## 5) شروط نجاح البوابة (Gate PASS)

يجب تحقق **جميع** ما يلي:

- `integrity:verify` — خروج `0`.
- `check.sh` — **PASS** (يشمل فحص لوجات، طوابير، صحة API، زمن استجابة؛ ومع `FAIL_ON_FAILED_JOBS=1` يفشل عند jobs عالقة).
- السيناريو الواقعي (§3) — ناجح.
- لا **backlog** حرج في الطوابير (ضمن عتبات `check.sh`).
- لا **أخطاء متكررة** في لوج التطبيق (ضمن عتبة `check.sh`).

---

## 6) شروط فشل البوابة (Gate FAIL)

أيٌ من:

- فشل `integrity:verify` (فاتورة بلا قيد، مخزون سالب، محفظة غير متوافقة، تكرار فاتورة، …).
- فشل `check.sh`.
- تكرار مالي، مخزون سالب، أو عدم استقرار workers (Restarting / فشل متكرر).

**عند الفشل — احتفظ بالأدلة:**

```bash
docker compose logs > failure.log
```

 وسجّل السبب في عمود **Notes** في §4.

---

## 7) شرط الانتقال — بدون زمن تقويمي

يُسمح بفتح **نطاق جديد** أو توسيع المنتج فقط إذا اكتُسب **استعراضٌ عملي** كالتالي (تُوثَّق في §4):

- **PASS مستمر** عبر **عدة نشرات / تشغيلات متتالية** على staging (العدد ليس رقمًا سحريًا — المقصود: تكرار موثوق بلا تراجع).
- **لا مشاكل مالية** (لا تكرار دفع، لا انحراف محفظة، لا قيود ناقصة حسب `integrity:verify`).
- **استقرار كامل** في queue/workworkers (ضمن `check.sh`).
- **لا drift** في البيانات (نفس أداة السلامة + مراقبة يدوية عند الحاجة).

حتى استيفاء ذلك: **EXECUTION LOCKED** — لا توسع.

---

## 8) القاعدة الحاكمة

```text
لا توسع
لا ميزات جديدة
لا تعديل مالي/مخزون خارج الإصلاح الضمني للنطاق
حتى يثبت الاستقرار فعلياً (شروط §7)
```

---

## 9) الأمن — تذكير مختصر (P0 / P1)

**P0 — قبل الإنتاج:** أسرار فقط على الخادم/CI؛ لا أسرار ثابتة في compose/صور؛ تدوير الافتراضيات؛ `.env` غير متعقّب.  

**P1 — قبل أول عميل:** عزل مستأجر؛ rate limit على login وAPI العامة وwebhooks؛ إيدمبوتنسي على مسارات الدفع/الترحيل/الاسترداد حيث يلزم.

*(تفصيل P2/P3: HTTPS/HSTS، composer audit، RBAC، تدقيق، استعادة من backup — كما في سياسة الفريق.)*

---

## 10) المالك والمراجعة

| الحقل | قيمة |
|--------|------|
| **آخر قرار بشأن النطاق** | _تاريخ + اسم_ |
| **مراجعة هذا المستند** | _تاريخ مقترح_ |

---

*مكمّلات تشغيلية في المستودع: `check.sh`، `php artisan integrity:verify`، `tests/Feature/PreProduction`.*
