# دليل حزمة النشر — AutoService SaaS Platform

## تعريف مختصر

منصة SaaS متعددة المستأجرين لمراكز صيانة المركبات وإدارة الأساطيل: Laravel (API) + Vue 3 (SPA) + PostgreSQL + Redis + Nginx + Docker Compose.

## مكونات هذه الحزمة

| المجلد / الملف | الوظيفة |
|----------------|---------|
| `backend/` | تطبيق Laravel — نقطة API، ترحيلات قاعدة البيانات، وظائف الطابور، تخزين، Dompdf، إلخ. |
| `frontend/` | كود مصدر الواجهة (Vue + Vite). يتطلب `npm ci` و`npm run build` لإنتاج `dist/` إلا إذا سُلّمت بناءً مسبقاً منفصلاً. |
| `docker/` | إعدادات Nginx و PHP (ملفات مرجعية للحاويات). |
| `docker-compose.yml` | تعريف الخدمات: app، nginx، postgres، redis، طوابير، واجهة، إلخ. |
| `Makefile` | أوامر مساعدة للتشغيل المحلي/الحاويات (اختياري). |
| `docs/` | وثائق تقنية مختارة (بدون `node_modules`). |
| `osas/docker-compose.yml` | مثال/بديل إن كان مستخدماً في بيئتكم. |

## المتطلبات التشغيلية

- **PHP:** 8.2+ (الحاوية الرسمية تستخدم 8.3 في `backend/Dockerfile`).
- **Composer:** 2.x (لتثبيت تبعيات PHP).
- **Node.js:** LTS حديث (للبناء الأمامي، مثلاً 20.x).
- **PostgreSQL:** متوافق مع إصدارات Laravel 11 المدعومة (غالباً 14+).
- **Redis:** 6/7 للجلسات والكاش والطوابير.
- **Nginx** أو خادم يعكس إلى PHP-FPM والواجهة الثابتة.

## رفع الملفات

1. ارفع محتويات **مجلد الحزمة** إلى خادم النشر (SFTP، Git deploy artifact، أو صورة Docker).
2. **لا** ترفع `node_modules/` أو `vendor/` — تُبنى على الخادم بـ `npm ci` و`composer install`.
3. ضع **`backend/.env`** من القالب (انظر `ENVIRONMENT_VARIABLES_REQUIRED.md` و`.env.example`).

## إعداد البيئة

- انسخ `.env.example` (جذر الحزمة أو `backend/.env.example`) إلى **`backend/.env`**.
- نفّذ `php artisan key:generate` داخل بيئة التطبيق إذا كان `APP_KEY` فارغاً.
- اضبط `APP_URL` و`APP_PUBLIC_URL` و`FRONTEND_PUBLIC_URL` على النطاق العام مع **HTTPS** في الإنتاج.

## أوامر التثبيت (مختصرة)

```bash
# PHP
cd backend
composer install --no-dev --optimize-autoloader

# الواجهة (من مجلد frontend في الحزمة)
cd ../frontend
npm ci
npm run build
```

> في Docker: نفّذ الأوامر داخل الحاوية المناسبة أو استخدم مراحل البناء في Dockerfile.

## أوامر البناء

- **الواجهة:** `npm run build` → مخرجات في `frontend/dist/`.
- **الخلفية:** لا يوجد compile إلزامي؛ يُفضّل `composer dump-autoload -o` بعد التثبيت.

## أوامر الترحيل

```bash
cd backend
php artisan migrate --force
```

## أوامر التشغيل (بعد الضبط)

- **Docker Compose:** من جذر الحزمة: `docker compose up -d` (راجع `DEPLOYMENT_STEPS.md`).
- **تحسين Laravel:**

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## صلاحيات المجلدات (Laravel)

- `backend/storage` و`backend/bootstrap/cache`: قابلية الكتابة لمستخدم خادم الويب/PHP (مثلاً `www-data` أو `nginx`).
- ربط التخزين العام: `php artisan storage:link` إن لزم.

## التحقق بعد النشر

- `GET /api/v1/health` — فحص صحة الخدمة.
- فتح الصفحة الرئيسية للواجهة ومسار تسجيل الدخول.
- مراجعة السجلات: `storage/logs/laravel.log` (بدون تسريب أسرار في الإنتاج).

## المسارات الأساسية

- API تحت البادئة: `/api/v1/...`
- صحة: `/api/v1/health`

## ملاحظات لفريق النشر

- **APP_DEBUG=false** و**APP_ENV=production** في الإنتاج.
- لا تُرفع ملفات `.env` الحقيقية إلى مستودع عام.
- **Sentry** و**قنوات الويبهوك** اختيارية — اضبطها فقط عند الحاجة.
- طوابير Redis: تشغيل `queue:work` أو حاويات الطابور كما في `docker-compose.yml`.

للتفاصيل الإضافية راجع: `DEPLOYMENT_STEPS.md`, `POST_DEPLOY_CHECKLIST.md`, `DIRECTORY_STRUCTURE.md`.
