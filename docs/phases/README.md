# مراحل الجاهزية (0–7) — قواعد العمل

## مبدأ عدم «إغلاق» المرحلة بدون بوابتين

لا تُعتبر أي مرحلة **مغلقة تشغيلياً** إلا إذا توفّر معاً:

1. **تقرير مفصل** لهذه المرحلة تحت `docs/phases/` (قالب التقدم أو الإغلاق).
2. **اختبارات مرتبطة بالمرحلة** وتشغيلها بنجاح في بيئة CI أو الجهاز المحلي (انظر `PHASE_TEST_REGISTRY.md`).

المراحل التي لا تزال قيد التوسع تُوثَّق كـ **تقرير تقدم** وليس «إغلاق نهائي»، حتى لا يُفهم أن النطاق توقف عند نقطة زمنية معيّنة بينما المنتج يتطور.

## الملفات

| الملف | الغرض |
|--------|--------|
| `PHASE_TEST_REGISTRY.md` | ربط كل مرحلة بأوامر الاختبار (خلفية / واجهة). |
| `PHASE_GATE_SCOPE.md` | **نطاق البوابة:** ما تغطيه المراحل مقابل PHPUnit / Vitest الكاملين. |
| `PHASE_00_CLOSURE_REPORT.md` | مرحلة 0 — أساس الكتالوج والتوثيق والبوابات الأولية. |
| `PHASE_01_PROGRESS_REPORT.md` … `PHASE_07_PROGRESS_REPORT.md` | تقارير تقدم لكل مرحلة مع قائمة تحقق ومسارات التحقق. |

## تشغيل اختبارات المرحلة (Laravel)

من مجلد `backend` (يتطلب `php` و`composer` في PATH)، أو عبر Docker من جذر المستودع:

```bash
docker compose run --rm app php vendor/bin/phpunit --group=phaseN
```

من مجلد `backend` مباشرة:

```bash
composer test:phase0
composer test:phase1
# … حتى test:phase7
composer test:phases   # تشغيل متسلسل لجميع المجموعات
```

أو مباشرة:

```bash
php vendor/bin/phpunit --group=phaseN
```

## من Makefile (جذر المستودع)

```bash
make fe-phases
make fe-phases-with-e2e
```

(يفترض تبعيات `frontend` مثبتة؛ انظر [`Makefile`](../Makefile).)

## Windows — مراحل الواجهة فقط (بدون `make`)

من جذر المستودع (بعد `cd frontend && npm ci` عند الحاجة):

```powershell
pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/fe-phases.ps1
pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/fe-phases-with-e2e.ps1
# أو إن لم يُثبَّت PowerShell 7:
powershell -NoProfile -ExecutionPolicy Bypass -File scripts/fe-phases.ps1
```

## Windows — بوابة Staging بدون Bash

مع تشغيل Docker:

```powershell
pwsh -NoProfile -ExecutionPolicy Bypass -File scripts/staging-gate.ps1
```

أو من Makefile: `make staging-gate-ps`.

## تشغيل اختبارات الواجهة حسب المرحلة (Vitest + Playwright)

من مجلد `frontend`:

```bash
npm run test:phase0   # توثيق مسارات + ملف نشاط/بوابات staff + توجيه ما بعد الدخول / onboarding
npm run test:phase1   # إدارة منصة (شريط جانبي، تنقل داخل الصفحة، تلميحات)
npm run test:phase2   # تدفق تشغيلي (فواتير/عمليات/سياق)
npm run test:phase3   # أخطاء API وتسجيل الدخول
npm run test:phase4   # عرض فواتير ووقت وتسميات
npm run test:phase5   # بوابات بورتالات + تكافؤ محلي
npm run test:phase6   # ذكاء المنصة (أنواع + composables + عروض)
npm run test:phase7   # Playwright: جاهزية عامة + ضيف + production-real-flow (الأخير يتخطى بدون PW_LOGIN_*)
npm run test:phases:fe          # Vitest فقط: المراحل 0 → 6 بالترتيب
npm run test:phases:fe:with-e2e # نفس السابق ثم test:phase7
```

## ربط CI

وظيفة **Staging gate** (`.github/workflows/staging-gate.yml`) تستدعي `scripts/staging-gate.sh` الذي يشغّل Vitest كاملاً ثم **PHPUnit للمراحل 0–7** (حيث **Auth** ضمن `phase0`) ثم **`php artisan ocr:verify --fail`**.  
وظيفة **`frontend-phase-gates`** تشغّل `npm run test:phases:fe` (**Vitest للواجهة 0–6**). **Playwright** يبقى في **`frontend-test-ci`** عبر `npm run test:ci`.
