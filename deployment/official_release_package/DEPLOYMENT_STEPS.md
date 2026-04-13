# خطوات النشر — مرتبة من الرفع إلى التحقق

> تفترض حزمة `official_release_package` مرفوعة إلى الخادم دون تعديل على المشروع الأصلي عند الإنشاء.

## 1) تجهيز الخادم

1. تثبيت Docker و Docker Compose **أو** تكديس PHP-FPM + Nginx + PostgreSQL + Redis يدوياً.
2. إنشاء مستخدم/قاعدة بيانات PostgreSQL وقاعدة فارغة للتطبيق.
3. التأكد من فتح المنافذ المطلوبة (80/443، 5432 داخلياً، 6379 داخلياً).

## 2) رفع الحزمة

1. رفع مجلد الحزمة إلى مسار النشر (مثلاً `/var/www/saas-release/`).
2. التحقق من عدم وجود ملف `.env` حقيقي داخل الحزمة المرفوعة من بيئة تطوير.

## 3) إعداد البيئة

1. نسخ القالب: `cp .env.example backend/.env` (أو دمج الحقول يدوياً مع `backend/.env.example`).
2. تعديل `backend/.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, قاعدة البيانات، Redis، إلخ.
3. داخل `backend`: `php artisan key:generate` (إذا كان `APP_KEY` فارغاً).

## 4) تبعيات PHP

```bash
cd backend
composer install --no-dev --optimize-autoloader
```

## 5) صلاحيات التخزين والكاش

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache
php artisan storage:link
```

(اضبط المستخدم حسب توزيعتكم.)

## 6) ترحيل قاعدة البيانات

```bash
php artisan migrate --force
```

## 7) بناء الواجهة الأمامية

```bash
cd ../frontend
npm ci
npm run build
```

رفع/ربط مجلد `frontend/dist/` بحيث يخدمه Nginx (أو خدمة `frontend` في Docker إن وُجدت).

## 8) ضبط Nginx / TLS

1. توجيه النطاق العام إلى خادم الويب.
2. شهادات TLS (Let’s Encrypt أو شهادة مؤسسية).
3. مطابقة `APP_URL` مع المخطط والنطاق الفعلي.

## 9) تحسين Laravel (إنتاج)

```bash
cd ../backend
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

> إذا ظهرت أخطاء في `route:cache` بسبب إغلاقات ديناميكية، راجع التوثيق أو أوقف `route:cache` مؤقتاً.

## 10) الطوابير والجدولة

1. تشغيل عمال الطابور: `php artisan queue:work redis` أو عبر خدمات `docker-compose` (queue_high، queue_default، queue_low).
2. جدولة Laravel: إضافة `* * * * * php artisan schedule:run` إلى crontab للمستخدم الصحيح أو حاوية منفصلة.

## 11) التشغيل

- مع Docker: من جذر الحزمة `docker compose up -d` ثم مراجعة `docker compose ps` و`docker compose logs`.
- بدون Docker: إعادة تشغيل PHP-FPM و Nginx.

## 12) التحقق النهائي

1. `curl -fsS https://your-domain/api/v1/health`
2. فتح الواجهة في المتصفح والتحقق من تحميل الأصول الثابتة.
3. تسجيل دخول اختباري بحساب إنتاجي مُنشأ وفق سياساتكم.
4. مراجعة `storage/logs/laravel.log` للأخطاء الحرجة.

راجع `POST_DEPLOY_CHECKLIST.md` للقائمة الكاملة.
