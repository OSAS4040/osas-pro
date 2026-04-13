# هيكل مجلد حزمة التسليم

## جذر الحزمة (`official_release_package/`)

| العنصر | يجب رفعه؟ | الوظيفة |
|--------|------------|---------|
| `README_DEPLOYMENT.md` | نعم | دليل النشر الرئيسي. |
| `DEPLOYMENT_STEPS.md` | نعم | خطوات مفصلة. |
| `ENVIRONMENT_VARIABLES_REQUIRED.md` | نعم | شرح المتغيرات. |
| `POST_DEPLOY_CHECKLIST.md` | نعم | قائمة تحقق. |
| `DIRECTORY_STRUCTURE.md` | نعم | هذا الملف. |
| `RELEASE_PREPARATION_REPORT.md` | نعم | تقرير التجهيز. |
| `.env.example` | نعم | قالب آمن؛ انسخه إلى `backend/.env`. |
| `docker-compose.yml` | نعم إن وُجد النشر عبر Compose | تجميع الخدمات. |
| `Makefile` | اختياري | اختصارات أوامر محلية. |
| `README.md` | اختياري | نظرة عامة على المشروع (من المصدر). |

## `backend/` — Laravel

| المجلد / ملف | الوظيفة |
|--------------|---------|
| `app/` | منطق التطبيق، المتحكمات، النماذج، الخدمات. |
| `bootstrap/` | إقلاع الإطار وكاش التمهيد. |
| `config/` | إعدادات Laravel. |
| `database/migrations/` | **ترحيلات المخطط** — تُنفَّذ عبر `php artisan migrate`. |
| `database/seeders/` | بذور اختيارية (حسب سياسة النشر). |
| `public/` | نقطة الدخول للويب (`index.php`) والأصول العامة. |
| `resources/` | Views، fonts لـ PDF، إلخ. |
| `routes/` | `api.php`, `web.php`, `console.php`. |
| `storage/` | تخزين التطبيق، السجلات، الكاش — **يتطلب صلاحيات كتابة**. |
| `artisan` | واجهة سطر أوامر Laravel. |
| `composer.json` / `composer.lock` | تبعيات PHP. |
| `Dockerfile` | بناء صورة PHP-FPM للحاوية. |
| `.env.example` | قالب Laravel الرسمي داخل الحزمة. |

**لا ترفع:** `vendor/` (يُنشأ بـ `composer install`)، ملفات `.env` الحقيقية.

## `frontend/` — Vue + Vite

| المجلد | الوظيفة |
|--------|---------|
| `src/` | كود المصدر (مكوّنات، views، stores). |
| `public/` | أصول ثابتة تُنسخ عند البناء. |
| `package.json` | سكربتات البناء والتبعيات. |
| `vite.config.*` | إعداد Vite. |
| `env.example` | قالب متغيرات الواجهة. |

**نقطة الدخول للمستخدم:** بعد `npm run build` المخرجات في **`frontend/dist/`** — يجب أن يوجّه Nginx إليها أو تُدمج في صورة Docker للواجهة.

**لا ترفع:** `node_modules/`، `dist/` إن أُعيد البناء على الخادم (يُنشآن محلياً).

## `docker/`

إعدادات **Nginx** وملفات PHP المساعدة المستخدمة مع `docker-compose.yml`.

## `docs/`

وثائق Markdown للمرجعية؛ **مُستبعد منها** `node_modules` في هذه الحزمة.

## `osas/`

ملف `docker-compose.yml` إضافي — يُستخدم فقط إذا كان ضمن إجراءات شركتكم.

---

## أين يُوضع ملف البيئة؟

- **Laravel:** `backend/.env` (يُنشأ من `.env.example` في جذر الحزمة أو `backend/.env.example`).
- **Vite (اختياري):** `frontend/.env` أو متغيرات البيئة في CI عند `npm run build`.
- **Docker Compose:** غالباً يشير `env_file` إلى `backend/.env` — راجع `docker-compose.yml`.

## الواجهة: مبنية أم على الخادم؟

- **الافتراضي في هذه الحزمة:** مصدر الواجهة موجود؛ **يُنصح ببناء الإنتاج على الخادم أو في CI** ثم نشر `dist/`.
- إذا سلّمتم `dist/` جاهزاً من خط أنابيب بناء موثوق، يمكن تضمينه في الحزمة كسياسة منفصلة (لم يُضمَّن افتراضياً لتجنب تعارض مع `npm run build`).
