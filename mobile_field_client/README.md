# mobile_field_client (Flutter — جديد)

مجلد **منفصل بالكامل** عن `frontend/` (Vue المنشور سابقاً). أي عمل Flutter/API clients/FCM/Reverb يُضاف هنا حتى لا تتداخل الحزم أو معرفات المتاجر مع ما نُشر من قبل.

## التحقق من Laravel (Docker — من جذر المستودع)

```bash
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan test tests/Feature/Auth/PushDeviceTest.php
docker compose exec -T app php artisan test tests/Feature/Auth/LoginTest.php
docker compose exec -T app php artisan test tests/Feature/Auth/AuthMobileApiFlowTest.php
```

لإعادة بناء **قاعدة الاختبار فقط** (`saas_test`):  
`docker compose exec -T app php artisan migrate:fresh --force --env=testing`

> ملاحظة: استخدام `php artisan test --filter=LoginTest` قد يطابق أكثر من ملف؛ تفضيل تشغيل **مسار الملف** كما أعلاه.

### FULL PASS (معايير الإغلاق)

| المعيار | الأمر / الإجراء |
|--------|------------------|
| Flutter deps | `cd mobile_field_client && flutter pub get` |
| Flutter static | `flutter analyze` |
| Firebase | `google-services.json` + `GoogleService-Info.plist` + `--dart-define` كما في الأسفل |
| E2E API (بدون جهاز) | الملف `tests/Feature/Auth/AuthMobileApiFlowTest.php` — يغطي: login → push-device (محاكاة refresh) → logout مع FCM → login → logout-all |
| E2E حقيقي على الجهاز | يدويًا: login → تسجيل FCM → logout → logout-all → التحقق من `onTokenRefresh` في السجلات/الخادم |

**STRONG CONDITIONAL PASS** يصبح **FULL PASS** عند إكمال الصفوف أعلاه على بيئةكم.

## الاسم والمعرّفات

| العنصر | القيمة الحالية (قابلة للتغيير) |
|--------|----------------------------------|
| اسم الحزمة (Dart) | `mobile_field_client` |
| تنظيم Android/iOS المقترح | عيّنوا `--org` خاصاً بكم عند الإنشاء (مثلاً `com.yourcompany.field`) |

**مهم للمتاجر:** استخدموا **Application ID / Bundle ID جديدين** لهذا التطبيق؛ لا تعيدوا استخدام معرفات البناء القديم.

## المتطلبات

- [Flutter SDK](https://docs.flutter.dev/get-started/install) مستقر على جهازكم (Windows/macOS/Linux).

## أول تشغيل بعد تثبيت Flutter

من جذر المستودع:

```bash
cd mobile_field_client
flutter --version
flutter pub get
flutter create .
flutter analyze
```

الأمر `flutter create .` **إلزامي قبل FULL PASS على الجهاز**: يُنشئ `android/` و`ios/` و`web/` حيث تُوضع ملفات Firebase:

| الملف (من وحدة تحكم Firebase) | المسار بعد `flutter create .` |
|------------------------------|----------------------------------|
| `google-services.json` | `android/app/google-services.json` |
| `GoogleService-Info.plist` | `ios/Runner/GoogleService-Info.plist` |

> **لا نضيف هذه الملفات من داخل المستودع**: محتواها خاص بمشروع Firebase لديكم. المستودع يتجاهلها في `.gitignore` إن رغبتم بعدم تتبعها؛ أو اتركوا التتبع إن كان المستودع خاصاً وسياسة الفريق تسمح بذلك (عدّلوا `.gitignore` حسب سياساتكم).

### Firebase (FCM) — `--dart-define`

عند توفر مشروع Firebase، مرّروا القيم (أو استخدموا `flutterfire configure` ثم استبدلوا التهيئة اليدوية لاحقاً):

```text
FIREBASE_PROJECT_ID
FIREBASE_API_KEY
FIREBASE_APP_ID
FIREBASE_MESSAGING_SENDER_ID
FIREBASE_STORAGE_BUCKET   (اختياري)
FIREBASE_IOS_BUNDLE_ID    (اختياري — iOS)
```

مثال تشغيل:

```bash
flutter run --dart-define=API_BASE_URL=https://your-host/api/v1 ^
  --dart-define=FIREBASE_PROJECT_ID=your-project ^
  --dart-define=FIREBASE_API_KEY=... ^
  --dart-define=FIREBASE_APP_ID=... ^
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=...
```

بدون هذه القيم يتخطّى التطبيق تهيئة Firebase بأمان (تحليل/CI بدون أسرار).

**Android:** أضيفوا `google-services.json` حسب وثائق FlutterFire. **iOS:** `GoogleService-Info.plist` + Push Notifications في Xcode.

### سياسة الخروج والدفع

- **خروج الجهاز الحالي:** يُرسل التطبيق `fcm_token` الحالي مع `POST /auth/logout` فيحذف الخادم صف `user_push_devices` المطابق ثم يُلغى توكن Sanctum الحالي.
- **خروج كل الأجهزة:** `POST /auth/logout-all` يحذف **كل** صفوف `user_push_devices` للمستخدم ثم كل توكنات Sanctum.

ثم:

```bash
flutter run --dart-define=API_BASE_URL=https://your-host/api/v1
```

## ما هو منفّذ (Mobile V1 — أساس إنتاجي)

- **Dio** على `API_BASE_URL` (افتراضي `http://127.0.0.1/api/v1`).
- **تسجيل دخول موحّد** عبر `identifier` + `password` + `device_*` (يتوافق مع `POST /api/v1/auth/login` في Laravel).
- **flutter_secure_storage** للتوكن ولقطة bootstrap من الخادم.
- **go_router** + `SessionController`: حراسة مسارات (UX) حسب `enabled_modules` و`home_screen` القادمين من الـ API.
- **تسجيل جهاز الدفع**: `AuthRepository.registerPushDevice` → `POST /api/v1/auth/push-device` (يُخزَّن في `user_push_devices` عبر Job في الطابور).
- **`PushMessagingBinding`**: `getToken` بعد الجلسة، `onTokenRefresh` لإعادة التسجيل، **تخطّي إعادة إرسال نفس التوكن** للخادم، و`deleteToken` بعد انتهاء الجلسة إذا سبق تسجيل ناجح.
- **سياسة أوفلاين**: تذكير في الواجهة فقط — لا كتابة مالية بدون خادم (راجع `lib/core/offline/offline_policy.dart`).

## ما يُضاف لاحقاً

- تكامل **firebase_messaging** في Flutter ثم استدعاء `AuthRepository.registerPushDevice` عند الحصول على التوكن (أو بعد كل `login` إن أردتم).
- Reverb في شاشات محددة + قنوات خاصة.
- توليد عميل Dart من OpenAPI (العقد مذكور في `backend/docs/openapi/auth-login-v1.fragment.yaml` + تعليقات l5-swagger).

## ملاحظة

هذا المجلد **ليس** بديلاً عن `frontend/`؛ الويب يبقى كما هو. التطبيق الميداني الجديد يعيش هنا فقط.
