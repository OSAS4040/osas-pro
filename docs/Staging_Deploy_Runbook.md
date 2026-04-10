# دليل النشر الآمن — Staging أولاً

**السياسة الملزمة للفريق:** [`Staging_Governance_Policy.md`](./Staging_Governance_Policy.md) · [`Staging_Gate_Mandatory_Policy.md`](./Staging_Gate_Mandatory_Policy.md) · [`Branch_Protection_And_Review_Policy.md`](./Branch_Protection_And_Review_Policy.md) (بوابة Staging + حماية `main`).

هذا المستند **ينفّذ القرار التشغيلي** المعتمد: التجربة على **staging** قبل الإنتاج، بمتغيرات **محافظة**، وفحص سريع بعد كل نشر، **دون** فتح صلاحيات خطرة في الإنتاج.

**الترتيب الحالي + قرار PASS/FAIL:** [`Staging_Execution_Now.md`](./Staging_Execution_Now.md).

## 1. المبدأ

| قاعدة | المعنى |
|--------|--------|
| Staging أولاً | أي تغيير في `SAAS_*` أو بناء واجهة بـ `VITE_*` يُختبر على بيئة شبيهة بالإنتاج قبل النقل. |
| متغيرات محافظة | افتراض «أقل امتياز»: لا تعديل كتالوج عالمي من المستأجرين؛ قائمة بريد منصة ضيقة؛ بوابات واجهة واضحة أو افتراض «الكل مفعّل». |
| فحص بعد كل نشر | آلية تلقائية (`make staging-gate`) + لمسات يدوية قصيرة (جدول أدناه). |
| إنتاج حازم | `SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT=false`؛ بريد منصة معروف فقط؛ لا `APP_DEBUG=true` في الإنتاج. |

## 2. ترتيب التغيير (مُنصح به)

1. نسخة واحدة في كل مرة عند الإمكان (مثلاً أولاً `backend/.env` ثم في نشر لاحق `frontend` build).
2. بعد كل نشر: تشغيل **`make staging-gate`** (أو ما يعادله في CI).
3. فحص يدوي سريع (دقيقتان): جدول القسم 4.
4. بعد التثبيت على staging: نقل القيم إلى الإنتاج **بنفس المنطق** (بدون تفعيل امتيازات إضافية «للتجربة» في الإنتاج).

## 3. متغيرات مرجعية

### الخادم (Laravel)

| المتغير | Staging (محافظ) | Production (لا تُخفّف) |
|-----------|------------------|-------------------------|
| `APP_ENV` | `staging` | `production` |
| `APP_DEBUG` | `false` يُفضّل | `false` |
| `SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT` | `false` | `false` |
| `SAAS_PLATFORM_ADMIN_EMAILS` | بريد **واحد** فريقي واضح | بريد (أو أكثر) تشغيل منصة فقط |

انظر أيضاً: [`backend/.env.staging.example`](../backend/.env.staging.example).

### الواجهة (Vite — وقت `build`)

| المتغير | ملاحظة |
|---------|--------|
| `VITE_DEPLOY_ENV` | `staging` أو `production` حسب البيئة. |
| `VITE_ENABLED_PORTALS` | فارغ = كل البوابات الاختيارية مفعّلة (آمن للتوافق). أو قائمة بيضاء صريحة بعد قرار المنتج. |

انظر: [`frontend/env.staging.example`](../frontend/env.staging.example).

## 4. فحص بعد النشر (يدوي)

**القائمة الكاملة بالترتيب:** [`Staging_Manual_Test_Checklist.md`](./Staging_Manual_Test_Checklist.md) (أتمتة → staff → مسارات → Admin API 200/403 → بوابات معطّلة → مراقبة).

### ملخص سريع (~2 دقيقة)

- [ ] دخول **فريق العمل** (staff) يعمل.
- [ ] فتح **`/about/taxonomy`** — يظهر المسرد.
- [ ] إن وُضع بريد في `SAAS_PLATFORM_ADMIN_EMAILS`: طلب `GET /api/v1/admin/companies` يعيد **200** مع توكن ذلك المستخدم؛ خلاف ذلك **403** متوقع.
- [ ] إن عُطّلت بوابة عبر `VITE_ENABLED_PORTALS`: التحقق من الإعادة التوجيه وليس شاشة بيضاء.

## 5. أتمتة سريعة في المستودع

```bash
make staging-gate
```

يشغّل: `npm ci` + Vitest في حاوية `frontend`، ثم PHPUnit لمسار المنصة/SaaS في حاوية `app` — نفس منطق [`scripts/staging-gate.sh`](../scripts/staging-gate.sh).

**تحقق سياسة أمثلة الإعداد (بدون Docker):**

```bash
make policy-env-example
# أو: node scripts/check-policy-env-example.mjs
```

**CI:** على كل PR نحو `main` (المسارات المذكورة في الملف) يُشغَّل workflow **Staging gate**؛ يتضمّن `policy-env-example` ثم Docker + `staging-gate.sh`.

للبوابة الكاملة قبل الدمج استخدم `make verify` أو `make release-gate` حسب [`Makefile`](../Makefile).

## 6. مراجع

- [`Staging_Manual_Test_Checklist.md`](./Staging_Manual_Test_Checklist.md) — قائمة الاختبار اليدوي على Staging.
- [`Platform_Safe_V1_Report.md`](./Platform_Safe_V1_Report.md) — السياق التقني لمسرد المنصة والبوابات.
