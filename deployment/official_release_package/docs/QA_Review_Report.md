# تقرير مراجعة واختبار — أسس برو / Workshop SaaS

**تاريخ التقرير:** 2026-04-06  
**البيئة:** Windows (PowerShell)، مجلد المشروع المحلي — **بدون PHP في PATH** و**بدون Docker نشط** أثناء جزء الخلفية.

---

## 1. ملخص تنفيذي

| المحور | النتيجة |
|--------|---------|
| بناء الواجهة الإنتاجي (`npm run build`) | **نجح** |
| فحص الأنواع (`vue-tsc --noEmit`) | **نجح** |
| Vitest (واجهة) | **11 اختباراً — نجاح** |
| ESLint (`--max-warnings 0`) | **نجح** |
| PHPUnit / `php artisan test` (خلفية) | **لم يُنفَّذ محلياً** — `php` غير متوفر في PATH على الجهاز المختبر |

**ملاحظة:** لتشغيل اختبارات Laravel استخدم أحد الخيارات: تثبيت PHP محلياً، أو `docker compose exec app php vendor/bin/phpunit`، أو بيئة CI.

---

## 2. ما تم تنفيذه (مؤكد في الكود والاختبارات)

### 2.1 الواجهة (Vue)

- **دخول موحّد** (`/login`): بريد + كلمة مرور، تحديد البوابة حسب الدور، بدون تبويبات بوابات منفصلة.
- **نسيت كلمة المرور** (`/forgot-password`) و**إعادة التعيين** (`/reset-password`) مع تسجيل في الراوتر.
- **OTP اختياري** عند تفعيله في الخادم: معالجة في `LoginView` و`PlatformAdminLoginView` و`auth` store.
- **التواصل مع الدعم** عبر `useSupportContact` ومتغيرات `VITE_SUPPORT_*`.
- **صفحة الهبوط — الباقات:** قسم `#pricing`، جلب `GET /api/v1/public/landing-plans` مع احتياطي محلي عند فشل الشبكة.
- **توليد أيقونات PWA** أثناء البناء: `scripts/generate-pwa-icons.mjs` → `public/pwa-192.png` و`pwa-512.png`.
- **PWA:** عند البناء، `vite-plugin-pwa` يولّد `manifest.webmanifest` وService Worker؛ `index.html` يضم `apple-touch-icon` ووسوم Apple؛ تعليمات التثبيت في `AppInstallHint`.

### 2.2 الخلفية (حسب المستودع السابق)

- مسارات: `forgot-password`، `reset-password`، `public/landing-plans`، منطق OTP في `AuthController` (عند التفعيل).
- إعدادات: `config/saas.php`، `config/landing.php`، أمثلة في `.env.example`.

---

## 3. أخطاء / فجوات مكتشفة

| البند | الخطورة | الوصف |
|--------|---------|--------|
| عدم تشغيل PHPUnit محلياً | متوسطة | لا يمكن تأكيد سلامة الخلفية على هذا الجهاز دون PHP/Docker. |
| PWA بدون Service Worker | منخفضة | يوجد **manifest + أيقونات** لدعم «إضافة للشاشة الرئيسية»، لكن **لا يوجد** `vite-plugin-pwa` / Workbox — لا تخزين مؤقت تلقائي ولا عمل دون شبكة للتطبيق كاملاً. |
| `index.html` عنوان عام | منخفضة | العنوان ما زال «نظام POS» وليس اسم المنتج النهائي (يمكن ربطه بـ `VITE_APP_NAME` عند الحاجة). |
| `favicon.ico` | منخفضة | المسار مذكور في `index.html`؛ التحقق من وجود الملف في `public/` يُنصح به عند النشر. |
| `manifest` ومسار فرعي (`BASE_URL`) | منخفضة | إذا نُشر التطبيق تحت مسار فرعي، قد تحتاج `start_url` و`scope` و`href` للـ manifest لتتوافق مع `import.meta.env.BASE_URL`. |
| ملفات `FleetLoginView` / `CustomerLoginView` | منخفضة | الراوتر يحوّل `/fleet/login` و`/customer/login` إلى `/login`؛ المكوّنات قديمة وقد لا تتوافق مع `LoginOutcome` إن أُعيد استخدامها لاحقاً. |

---

## 4. ما لم يُنفَّذ أو خارج نطاق المراجعة

1. **تطبيق جوال أصلي** (Flutter / React Native) — غير موجود في المستودع؛ الوثائق تقترح PWA ثم تطبيق لاحقاً.
2. **OTP عبر SMS** — التنفيذ الحالي (حسب السياق) يعتمد على البريد؛ تكامل مزوّد رسائل نصية لم يُختبر هنا.
3. **لوحة ويب لتعديل باقات الهبوط** — المحتوى من `config/landing.php` أو API؛ واجهة إدارة قابلة للسحب والإفلات غير مذكورة كمنجز.
4. **اختبار قبول يدوي (UAT)** و**نشر إنتاج** — مسؤولية التشغيل.
5. **تشغيل كامل `php artisan test`** أو **`make verify`** — يتطلب بيئة متكاملة (قاعدة بيانات اختبار، Redis، إلخ).

---

## 5. أوامر مُوصى بها لإعادة التحقق

```bash
# واجهة
cd frontend
npm run build
npm run test
npm run lint:check
npm run type-check
```

```bash
# خلفية (داخل حاوية Laravel أو مع PHP محلي)
cd backend
vendor/bin/phpunit
# أو
php artisan test
```

---

## 6. الخلاصة

الواجهة **تبنى وتُفحص أنواعياً وتمرّ اختبارات الوحدة الحالية وتمرّ ESLint** في البيئة المختبرة. **الخلفية لم تُختبر آلياً** على هذا الجهاز لغياب PHP. **PWA**: أصبحت هناك **أيقونات + manifest** لتحسين التثبيت على الجوال؛ **Service Worker كامل** يبقى تحسيناً اختيارياً لاحقاً.

---

*نهاية التقرير.*
