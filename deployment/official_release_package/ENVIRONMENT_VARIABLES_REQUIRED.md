# متغيرات البيئة المطلوبة

> القيم أدناه **أمثلة آمنة** — استبدلها بقيمكم دون نشر أسرار في git.

| المتغير | الغرض | إلزامي؟ | مثال (غير حقيقي) | ملاحظات |
|---------|--------|---------|-------------------|---------|
| `APP_NAME` | اسم التطبيق في الواجهات والبريد | موصى به | `SaaS POS ERP` | |
| `APP_ENV` | بيئة التشغيل | **نعم** | `production` | يجب ألا تكون `local` في الإنتاج العام. |
| `APP_KEY` | مفتاح التشفير Laravel | **نعم** | (ناتج `php artisan key:generate`) | لا تشاركه علناً. |
| `APP_DEBUG` | وضع التصحيح | **نعم** | `false` | `true` في الإنتاج يعرّض التفاصيل. |
| `APP_URL` | رابط التطبيق الأساسي | **نعم** | `https://app.example.com` | يُستخدم في الروابط والمهام. |
| `APP_PUBLIC_URL` | نطاق يراه المستخدم للروابط العامة/QR | موصى به | `https://app.example.com` | يُفضّل مطابقة `APP_URL` إن أمكن. |
| `FRONTEND_PUBLIC_URL` | واجهة SPA للمستخدم | موصى به | `https://app.example.com` | يُستخدم لإعادة التوجيه من Laravel. |
| `DB_CONNECTION` | نوع قاعدة البيانات | **نعم** | `pgsql` | المشروع مهيأ لـ PostgreSQL. |
| `DB_HOST` | مستضيف PostgreSQL | **نعم** | `127.0.0.1` أو `postgres` داخل Docker | |
| `DB_PORT` | المنفذ | **نعم** | `5432` | |
| `DB_DATABASE` | اسم القاعدة | **نعم** | `saas_db` | أنشئ القاعدة مسبقاً. |
| `DB_USERNAME` | مستخدم القاعدة | **نعم** | `saas_user` | صلاحيات DDL/DML حسب سياسة الأمان. |
| `DB_PASSWORD` | كلمة مرور القاعدة | **نعم** | `CHANGE_ME_STRONG_PASSWORD` | لا تُخزَّن في المستودع. |
| `REDIS_HOST` | Redis | **نعم** (للكاش/الجلسة/الطابور كما في القالب) | `127.0.0.1` | |
| `REDIS_PORT` | منفذ Redis | **نعم** | `6379` | |
| `REDIS_PASSWORD` | كلمة مرور Redis | اختياري | `null` أو كلمة قوية | إن فُعّلت في Redis. |
| `CACHE_DRIVER` | محرك الكاش | موصى به | `redis` | |
| `QUEUE_CONNECTION` | محرك الطابور | موصى به | `redis` | |
| `SESSION_DRIVER` | محرك الجلسات | موصى به | `redis` | |
| `SESSION_LIFETIME` | دقائق الجلسة | اختياري | `120` | |
| `ZATCA_SIMULATION_MODE` | وضع محاكاة ZATCA | موصى به | `true` حتى اكتمال التكامل الإنتاجي | |
| `OCR_*` | إعدادات Tesseract | اختياري | كما في `.env.example` | عطّل `OCR_ENABLED=false` إن لم يُثبَّت Tesseract. |
| `INTELLIGENT_*` | ميزات الذكاء/المراقبة | اختياري | `false` افتراضياً آمن | فعّل عند الحاجة والترخيص. |
| `SAAS_PLATFORM_ADMIN_EMAILS` | مشغّلو المنصة | موصى به | `ops@example.com` | |
| `LEDGER_ALERT_WEBHOOK_URL` | تنبيهات محاسبية | اختياري | فارغ | HTTPS فقط. |

### الواجهة (Vite — ملف منفصل عادةً `frontend/.env` أو متغيرات وقت البناء)

| المتغير | الغرض | إلزامي؟ | مثال |
|---------|--------|---------|------|
| `VITE_API_BASE_URL` | مسار API | اختياري إذا كان النسبي `/api/v1` | فارغ أو `https://app.example.com` |
| `VITE_DEPLOY_ENV` | بيئة البناء | اختياري | `production` |
| `VITE_PUBLIC_SITE_URL` | SEO/OG | اختياري | `https://example.com` |

راجع `frontend/env.example` داخل الحزمة للقائمة الكاملة الاختيارية.

### Docker / ngrok (جذر المستودع — إن استخدمتم الملفات المرفقة)

| المتغير | الغرض |
|---------|--------|
| `NGROK_AUTHTOKEN` | رمز ngrok للنفق (تطوير/عرض فقط) |
| `NGROK_DOMAIN` | نطاق محجوز |

لا تُستخدم عادةً في إنتاج نهائي بدون ngrok.
