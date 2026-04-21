# ترتيب التنفيذ الرسمي — أسس برو (تدريجي وإلزامي)

**القاعدة:** لا تُكمَل مرحلة قبل إكمال السابقة **إلا** حيث يُذكر «يمكن بالتوازي».

| المرجع | الملف |
|--------|--------|
| سياسة إغلاق Staging | [`Staging_Governance_Policy.md`](./Staging_Governance_Policy.md) |
| بوابة Staging إلزامية | [`Staging_Gate_Mandatory_Policy.md`](./Staging_Gate_Mandatory_Policy.md) |
| حماية `main` + مراجعة | [`Branch_Protection_And_Review_Policy.md`](./Branch_Protection_And_Review_Policy.md) |
| دليل النشر | [`Staging_Deploy_Runbook.md`](./Staging_Deploy_Runbook.md) |
| إعداد GitHub (يدوي) | [`GitHub_Branch_Protection_Setup.md`](./GitHub_Branch_Protection_Setup.md) |

---

## طبقات تنفيذ المنتج (فنية واحترافية) — Contract Layer والأنشطة المتعددة

**اعتبار إلزامي — عدة أنشطة:** المنصة تدعم **أكثر من نموذج تشغيل** (مستأجرون وأنواع أعمال مختلفة). لا يُفترض تصميم أو تنفيذ أي ميزة على أساس «نشاط واحد فقط» (مثل ورشة دون غيرها) إلا إن وُثّق استثناء صريح. عند كل تطوير يُراجع:

- **نوع النشاط / ملف العمل** للمستأجر (وما يعادله في الخادم والواجهة، مثل مصفوفة الميزات الفعّالة).
- هل السلوك **صالحاً لكل الأنشطة المدعومة** أم يحتاج **شرط نشاط**، أو **قيمة افتراضية آمنة** عند تعطيل الميزة لهذا النشاط.
- **الاختبار:** سيناريو على الأقل لنشاطين مختلفين عندما تمس الميزة السلوك التشغيلي أو المالي.

### Contract Layer (طبقة العقود)

تُنفَّذ **بعد** تثبيت حوكمة المستودع وبيئة Staging الأساسية (مراحل 0–3)، و**قبل** أو **بالتوازي المعقول** مع توسيع الواجهات التي تلمس التزامات خارجية (مورد، عميل، أسطول). المقصود ليس «ملفاً قانونياً» فقط، بل **طبقة منتج** تربط الالتزام بالتشغيل:

1. **عقود تشغيلية وإثبات:** عقود موردين (وثائق، تواريخ انتهاء، تنبيهات)، وربطها بمسارات المشتريات والتدقيق حيث تنطبق؛ وتوسيع منطقي لعقود/التزامات أخرى حسب خارطة الطريق دون خلطها بمسودات غير ملزمة.
2. **وعي بالنشاط:** حقول العقد، القوائم، والصلاحيات قد تختلف أو تُخفى حسب نوع النشاط، مع **نموذج بيانات مشترك** لا ينكسر عند تبديل المستأجر.
3. **عقود واجهة بين الوحدات (مُستحسن للفريق):** استقرار شكل الـ API والأحداث بين الوحدات (إصدارات، تجنب كسر المستهلكين الداخليين) عند إضافة نشاط أو بوابة جديدة.
4. **أمان وامتثال:** من يرى التكلفة أو البنود الحساسة يُقرَّر على **الخادم**؛ لا تعتمد الواجهة وحدها على «إخفاء» دون سياسة.

### تسلسل الطبقات (ملخّص للتنفيذ)

| الترتيب | الطبقة | مضمون مختصر |
|--------|--------|-------------|
| 1 | **أساس الحوكمة والبيئة** | مراحل 0–3 في هذا الملف (مستودع، GitHub، Staging، مصادر حقيقة للصلاحيات والميزات). |
| 2 | **Contract Layer** | عقود تشغيلية، ربط مشتريات/تنبيهات، واجهات مستقرة، بدون افتراض نشاط واحد. |
| 3 | **الشفافية التشغيلية** | لوحة قدرات من الخادم: واجهة [`/about/capabilities`](../frontend/src/views/about/SystemCapabilitiesView.vue) · `GET /api/v1/system/capabilities` (قراءة، `throttle`، workshop-side فقط). عرض: متاح / مقيد بالنشاط أو الدور / مخطط + سبب آمن. |
| 4 | **مالي وضريبة وفواتير** | أولوية امتثال؛ اختبارات لأنشطة مختلفة عند اختلاف السلوك المحاسبي. |
| 5 | **تشغيل ميداني مركزي (ويب)** | اعتماد أوامر عمل، محفظة/أسطول، تكامل مع طبقة العقود حيث يلتقي الالتزام بالتنفيذ. |
| 6 | **توسعات لاحقة (بعد النشر)** | **بوابة المورد: ملغاة** ضمن النطاق الحالي (التعامل عبر شاشات المنشأة). **تطبيق ميداني أصلي:** مرحلة بعد نسخة النشر والاستقرار، وليس ضمن الإطلاق الأول. |
| 7 | **تمييز وتقارير وأتمتة** | خرائط متقدمة، فلترة، صيانة وقائية آلية بعد ثبات جودة البيانات. |

*الترقيم أعلاه **دليل تنفيذ** يكمّل المراحل 0–5؛ عند التعارض، يُفضَّل عدم تجاوز قيود Staging والإنتاج الواردة في السياسات المرتبطة.*

---

## المرحلة 0 — جاهزية المستودع (مكتملة إن وُجد ما يلي)

- [x] يوجد workflow [`.github/workflows/policy-env-on-pr.yml`](../.github/workflows/policy-env-on-pr.yml) (فحص env على كل PR + `permissions: contents: read`)
- [x] يوجد workflow [`.github/workflows/staging-gate.yml`](../.github/workflows/staging-gate.yml) (migrate ثم `dev:demo-seed` في CI عندما `APP_ENV=local`)
- [x] يوجد [`scripts/staging-gate.sh`](../scripts/staging-gate.sh) و`make staging-gate` و`make policy-env-example` في [`Makefile`](../Makefile)
- [x] يوجد [قالب PR](../.github/PULL_REQUEST_TEMPLATE.md)
- [x] تحديثات تبعيات: [`.github/dependabot.yml`](../.github/dependabot.yml) (Composer + npm أسبوعياً)

*عند اكتمال المرحلة 1 يدوياً على GitHub: الحوكمة تصبح إلزامية تقنياً على `main`.*

---

## المرحلة 1 — مسؤول GitHub (إلزامي قبل اعتبار الحوكمة «مفعّلة تقنياً»)

**الضبط على GitHub** يدوي (صلاحية Admin). **التحقق الآمن من الطرفية** بعد الضبط: `make github-branch-protection-status` — انظر [`gh-branch-protection-status.mjs`](../scripts/gh-branch-protection-status.mjs).

1. [ ] قراءة [`GitHub_Branch_Protection_Setup.md`](./GitHub_Branch_Protection_Setup.md) كاملاً (حماية كلاسيكية أو **Rulesets**).
2. [ ] تشغيل workflow **Policy env on PR** على PR نحو `main` مرة على الأقل (أو **Actions** → Run workflow للتجربة)، حتى تظهر **`Policy env on PR / policy-env-example`** في قائمة الفحوص المطلوبة.
3. [ ] (اختياري) تشغيل **Staging gate** عبر `workflow_dispatch` أو PR يطابق المسارات — لإضافة **`Staging gate / staging-gate`** إن رغبتم؛ انظر تحذير المسارات في [`GitHub_Branch_Protection_Setup.md`](./GitHub_Branch_Protection_Setup.md).
4. [ ] حماية **`main`** (فرع أو Ruleset): PR إلزامي، فحوص إلزامية، **`Policy env on PR / policy-env-example`** ضمن المطلوب، تضييق bypass، **Block force pushes**، ومنع push مباشر حسب سياسة المنظمة.
5. [ ] التحقق: PR تجريبي — لا يُدمَج قبل نجاح **`Policy env on PR / policy-env-example`** على الأقل.
6. [ ] (مُستحسن) `gh auth login` ثم **`make github-branch-protection-status`** — خروج بنجاح ويظهر تضمين فحص السياسة.

**عند إكمال المرحلة 1:** الحوكمة على `main` أصبحت **شرطاً تقنياً** وليس وثيقة فقط.

---

## المرحلة 2 — ضبط الفريق (بالتوازي مع 1 أو مباشرة بعدها)

- [ ] إبلاغ الفريق: المرجع الوحيد للترتيب هو **هذا الملف** + السياسات المرتبطة أعلاه، بما فيها قسم **«طبقات تنفيذ المنتج — Contract Layer والأنشطة المتعددة»**.
- [ ] أي PR يمس المنصة / SaaS / البيئة / الصلاحيات / **العقود أو مسارات الموردين** / **مصفوفة الميزات أو نوع النشاط**: **تعبئة القالب** + **`make policy-env-example`** + **`make staging-gate`** محلياً إذا لم يُشغِّل CI المسار تلقائياً.

---

## المرحلة 3 — بيئة Staging حقيقية (بعد 1 و2)

1. [ ] نسخ [`backend/.env.staging.example`](../backend/.env.staging.example) و[`frontend/env.staging.example`](../frontend/env.staging.example) إلى أسرار البيئة الفعلية (لا تُرفع إلى git).
2. [ ] نشر **الخادم** و**الواجهة** على عنوان staging مع متغيرات **محافظة** (`SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=false`، بريد منصة ضيق، إلخ).
3. [ ] اتبع [`Staging_Execution_Now.md`](./Staging_Execution_Now.md) (ترتيب + `make policy-env-example` + `make staging-gate` + قائمة يدوية كاملة + نتيجة **PASS / PASS مع ملاحظات / FAIL**).

---

## المرحلة 4 — الإنتاج (بعد استقرار المرحلة 3)

- [ ] نتيجة Staging اليدوي **PASS** أو **PASS مع ملاحظات غير حرجة** (انظر [`Staging_Execution_Now.md`](./Staging_Execution_Now.md)) — **لا انتقال** عند **FAIL** حتى يُعالَج ويُعاد الاختبار.
- [ ] نقل **نفس المنطق** و**نفس القيود** إلى الإنتاج — **دون** توسيع امتيازات أو تفعيل `SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=true`.
- [ ] فحص يدوي مختصر + مراقبة صحة الخدمات بعد النشر.

---

## المرحلة 5 — تحسينات مساندة (اختيارية، بعد ثبات 1–4)

- [x] قالب [.github/CODEOWNERS](../.github/CODEOWNERS) جاهز — فعّل الأسطر بعد استبدال `@your-org/...` من [`CODEOWNERS.example`](./CODEOWNERS.example).
- [ ] (اختياري) hook محلي قبل `commit` لتشغيل `node scripts/check-policy-env-example.mjs` عند تعديل ملفات env.

---

## ما يُؤجَّل (حتى إشعار آخر من الإدارة المنتج)

راجع [`Branch_Protection_And_Review_Policy.md`](./Branch_Protection_And_Review_Policy.md) — اشتراك عميل↔منصة، محفظة/دفتر منصة، هوية منصة كبيرة، إلخ.

---

## أوامر سريعة (من جذر المستودع، مع Docker)

```bash
make policy-env-example
make staging-gate
```

من مجلد `frontend` (بدون Docker للتحقق السريع من سياسة env):

```bash
npm run policy:env
```
