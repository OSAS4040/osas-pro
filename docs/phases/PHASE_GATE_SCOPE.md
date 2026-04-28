# نطاق بوابة المراحل (0–7) مقابل مجموعة الاختبارات الكاملة

## الهدف

مجموعات **`phase0` … `phase7`** في PHPUnit، وسكربتات **`test:phaseN`** في الواجهة، هي **بوابة جودة مختارة** (سريعة نسبياً وتغطي مسارات حرجة متفقاً عليها)، وليست تعادل «تشغيل كل اختبارات `tests/Feature`».

## الخلفية (Laravel / PHPUnit)

| الواقع | تفصيل |
|--------|--------|
| **ما تفعله البوابة (PHPUnit فقط)** | `composer test:phases` يشغّل `--group=phase0` ثم … `phase7` بالترتيب — **لا يشمل** Vitest ولا **OCR**. |
| **بوابة Staging الكاملة (Docker)** | [`scripts/staging-gate.sh`](../../scripts/staging-gate.sh): Vitest في `frontend` ثم PHPUnit بالمراحل ثم **`php artisan ocr:verify --fail`** في `app`. لتغطية OCR بدون تشغيل كل البوابة: **`make ocr-verify`**. |
| **ما لا تفعله تلقائياً** | بقية ملفات `tests/Feature/**/*.php` التي **لا** تحمل وسوم `phaseN` لا تُشغَّل ضمن هذه الحلقة. |
| **حجم (مُحدَّث من المستودع)** | تحت `tests/Feature/` يوجد **103** ملف `*Test.php`؛ **37** منها يحتوي على وسوم **`#[Group('phase0')]` … `#[Group('phase7')]`** (نحو **36%** من ملفات Feature — بوابة مركّزة لمسارات محددة: مصادقة، كتالوج، محفظة، تعارض، محاسبة، حوكمة، منصة، ما قبل الإنتاج، إلخ). |
| **التشغيل الكامل** | `cd backend && ./vendor/bin/phpunit` أو `make verify` / `make test-project-gate` حسب [`Makefile`](../../Makefile) وسياسة الفريق. |

## الواجهة (Vitest / Playwright)

| الواقع | تفصيل |
|--------|--------|
| **`npm run test:phases:fe`** | يشغّل **Vitest** للمراحل **0→6** بالترتيب (حزم ملفات محددة في `frontend/package.json`). |
| **`npm run test:phase7`** | **Playwright** لثلاثة ملفات E2E (جاهزية عامة + ضيف + مسار اختياري ببيانات دخول). |
| **`npm run test`** في الواجهة | يشغّل **كل** ملفات `*.test.ts` تحت `src/` — أوسع من حزم المراحل. |

## ماذا يُعتبر «مكتملاً» هنا؟

| الطبقة | حالة «التجهيز» |
|--------|----------------|
| أوامر `composer test:phaseN` و`test:phases` | مكتمل |
| تسلسل Staging + Docker لـ PHPUnit بالمراحل | مكتمل |
| سكربتات الواجهة `test:phase0`…`7` و`test:phases:fe` | مكتمل |
| CI: `frontend-phase-gates` + `staging-gate` | مكتمل (حسب المسارات في workflow) |
| تغطية **كل** Feature tests بوسوم مراحل | **غير مطلوب** — قرار منتج/تدريجي عند إضافة بوابات جديدة |

## الخطوة التالية (اختياري)

عند إضافة مجال جديد يجب حمايته في كل PR: أضف `#[Group('phaseK')]` لاختبارات Feature ذات الصلة، حدّث `PHASE_TEST_REGISTRY.md`، ووسّع سكربت الواجهة إن وُجدت وحدات Vitest مطابقة.
