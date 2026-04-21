# إعداد OCR (Tesseract) في Docker و Laravel

## الغرض

تشغيل استخراج النص من الصور (مسح لوحة المركبة، OCR للفواتير، مستندات المركبات) داخل **نفس حاوية** تطبيق PHP (`php-fpm`)، مع تمييز واضح بين:

- **محرك OCR غير متاح** — الثنائي أو حزم اللغات غير مثبتة في الحاوية، أو `OCR_ENABLED=false`.
- **تعذّر القراءة** — المحرك يعمل لكن الصورة غير صالحة، فارغة الناتج، أو كبيرة جداً.

## المتطلبات في Docker

### الصورة الحالية (Alpine — `backend/Dockerfile`)

يُثبَّت تلقائياً:

- `tesseract-ocr`
- `tesseract-ocr-data-eng`
- `tesseract-ocr-data-ara`

المسار الشائع للثنائي: `/usr/bin/tesseract`.

### صورة Debian/Ubuntu (مرجع)

إذا استخدمت أساساً مبنياً على `apt`:

```dockerfile
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr \
    tesseract-ocr-ara \
    tesseract-ocr-eng \
    && rm -rf /var/lib/apt/lists/*
```

**مهم:** تثبيت Tesseract على الجهاز المضيف (Windows/macOS) لا يكفي إن كان الطلب يُنفَّذ داخل Docker؛ يجب أن تكون الحزم داخل الحاوية التي تشغّل Laravel.

## التحقق داخل الحاوية

بعد `docker compose build` و`docker compose up`:

```bash
docker compose exec app tesseract --version
docker compose exec app tesseract --list-langs
```

تأكد من ظهور اللغتين `ara` و `eng` في قائمة `--list-langs`.

## التحقق من Laravel

داخل الحاوية:

```bash
docker compose exec app php artisan ocr:verify
```

للاستخدام في CI مع فشل عند عدم الجاهزية:

```bash
docker compose exec app php artisan ocr:verify --fail
```

## متغيرات البيئة

انظر `backend/.env.example` (قسم OCR):

| المتغير | الوصف |
|--------|--------|
| `OCR_ENABLED` | `true`/`false` — تعطيل كامل لاستدعاء Tesseract |
| `OCR_TESSERACT_PATH` | مسار ثنائي صريح؛ فارغ = اكتشاف تلقائي |
| `OCR_DEFAULT_LANG_PLATE` | افتراضي `eng+ara` (لوحة) |
| `OCR_DEFAULT_LANG_DOCUMENT` | افتراضي `ara+eng` (فواتير/مستندات) |
| `OCR_TIMEOUT_SECONDS` | مهلة تشغيل Tesseract (ثوانٍ) |
| `OCR_MAX_IMAGE_BYTES` | رفض الصور الأكبر من هذا الحجم |
| `OCR_REQUIRED_LANGS` | للتحقق في `ocr:verify` (مثلاً `ara,eng`) |

## مسار الكود (Laravel)

- الإعدادات: `config/ocr.php`
- التنفيذ وفصل حالات الفشل: `App\Services\Ocr\TesseractOcrRunner`
- نقاط HTTP: `App\Http\Controllers\Api\V1\OcrController` (`scanPlate`, `scanInvoice`, `scanVehicleDocument`)
- أمر التشغيل: `php artisan ocr:verify` — `App\Console\Commands\OcrVerifyCommand`

## اختبار يدوي سريع (صورة)

1. ضع ملف PNG/JPEG واضح في الحاوية (مثلاً عبر `docker cp`).
2. من داخل الحاوية:

```bash
docker compose exec app tesseract /path/in/container/plate.png stdout -l eng+ara --oem 3 --psm 7
```

3. أو استدعِ واجهة API لمسح اللوحة (مع مصادقة Sanctum وصلاحيات الحوكمة المناسبة): `POST /api/v1/governance/ocr/plate` مع حقل `image` (base64).

**ملاحظة:** حاوية `php-fpm` الحالية لا تتضمن امتداد GD؛ توليد صور برمجياً للاختبار يتم خارج الحاوية أو بأداة أخرى. التحقق الرسمي للبيئة هو `php artisan ocr:verify`.

## السجلات (logging)

يُسجَّل على مستوى التطبيق (بدون إرسال stack trace للمستخدم):

- عدم العثور على ثنائي Tesseract
- خروج Tesseract برمز خطأ
- استثناء أثناء التشغيل

## استكشاف الأعطال

| العرض | السبب المحتمل |
|--------|----------------|
| «محرك OCR غير متاح على الخادم (Tesseract)» | الحزم غير مثبتة في الحاوية، أو مسار خاطئ، أو `OCR_ENABLED=false` |
| «تعذّر قراءة اللوحة تلقائياً…» | المحرك يعمل؛ صورة رديئة، تنسيق غير مدعوم، أو فشل Tesseract |
| «حجم الصورة كبير جداً» | زِد `OCR_MAX_IMAGE_BYTES` أو صغّر الصورة من الواجهة |
