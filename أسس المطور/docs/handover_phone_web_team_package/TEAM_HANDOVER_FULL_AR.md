# تسليم فريق النشر — مسار الويب: تسجيل الدخول، التسجيل، الجوال + OTP

**جمهور الوثيقة:** التشغيل، DevOps، ضمان الجودة، المنتج.  
**الغرض:** مرجع واحد للنشر والتحقق دون الحاجة لتتبع سجل المحادثات.

**قبل أي شيء:** اقرأوا **`WHAT_ZIP_CONTAINS_AR.md`** — يوضح أن الأرشيف **ليس** استبدالاً لرفع تطبيق Laravel+Vue كاملاً من ملف صغير، وما البديل (Git، أو حزمة الإصدار الرسمية، أو Docker).

---

## أ) هل هذه الحزمة تحتوي «الكود»؟

| ما يُرفق هنا | ما لا يُرفق هنا |
|----------------|------------------|
| وصف النطاق، المسارات، الـ API، الإعدادات، الاختبارات، **قائمة مسارات الملفات في المستودع** | **شفرة المصدر** (ملفات `.php` / `.vue` / `.ts` كاملة) |

**الخلاصة:** الحزمة **وثائق تسليم** للقراءة والتوافق. **الرفع الفعلي** يتم من فرع/إصدار المشروع في Git (Pipeline أو يدوياً: `git pull` → `migrate` → `build` → نشر الأصول).  
من يريد مراجعة التنفيذ يفتح المسارات المذكورة أدناه في نفس نسخة الكود المُنشرة.

---

## ب) النطاق والحدود

### ضمن النطاق (ويب)
- تسجيل الدخول الحالي **بالبريد أو الجوال + كلمة المرور** — **بدون كسر**.
- مسار **إضافي**: **جوال + OTP** بدون كلمة مرور في هذا المسار، ثم إكمال نوع الحساب (فرد/شركة) والحقول الدنيا.
- شركات: بعد الإدخال تبقى **بانتظار مراجعة المنصة**؛ لا شركة تشغيلية كاملة تلقائياً قبل الموافقة.
- لوحة داخلية لطابور طلبات الشركات (مسار ويب للمشغّلين المصرّح لهم).

### خارج النطاق
- **`mobile_field_client` (Flutter):** غير مشمول؛ مسار الجوال **للويب فقط** حتى يُقرَّر لاحقاً.
- لا يُطلب بريد أو كلمة مرور في **بداية** مسار الجوال؛ لا سجل تجاري/ضريبي في البداية.

---

## ج) مسارات الواجهة (Vue — SPA)

| المسار | الوظيفة |
|--------|---------|
| `/login` | دخول تقليدي + رابط «دخول برمز الجوال» → `/phone` |
| `/register` | تسجيل شركة تقليدي (بريد + كلمة مرور + النموذج) — **لم يُستبدل** |
| `/phone` | طلب OTP |
| `/phone/verify` | التحقق من الرمز (`?phone=` في الاستعلام) |
| `/phone/onboarding` | محور التوجيه حسب حالة التسجيل |
| `/phone/onboarding/type` | فرد / شركة |
| `/phone/onboarding/individual` | الاسم الكامل |
| `/phone/onboarding/company` | اسم المنشأة + مسؤول الاتصال |
| `/phone/onboarding/pending-review` | انتظار مراجعة الشركة |
| `/phone/onboarding/done` | بعد إكمال الفرد — إرشاد وربط لاحق |
| `/admin/registration-profiles` | طابور مراجعة الشركات (صلاحيات منصة) |

**حارس المسارات:** مستخدم `phone_onboarding` لا يتصفح تطبيق الورشة خارج `/phone/...` إلا صفحات **`meta.publicPage`** (مثل `/landing`) مع شريط عودة.

---

## د) واجهات API (Laravel — تحت `/api/v1`)

> البادئة الفعلية: `/api/v1` (كما يضبطها الواجه في `apiClient`).

### عامة (بدون Bearer)
| الطريقة | المسار | ملاحظة |
|---------|--------|----------|
| POST | `/auth/phone/request-otp` | جسم JSON: `phone` — **throttle** على المسار |
| POST | `/auth/phone/verify-otp` | جسم: `phone`, `otp` — **throttle** |

### بعد التوكن (Sanctum)
| الطريقة | المسار | ملاحظة |
|---------|--------|----------|
| GET | `/auth/registration-status` | حالة الخطوات |
| POST | `/auth/complete-account-type` | يتطلب صلاحية `phone_registration.flow` |
| POST | `/auth/complete-individual-profile` | |
| POST | `/auth/complete-company-profile` | |

### منصة (مشغّل منصة — حسب تجميع المسارات في `routes/api.php`)
| الطريقة | المسار (نسبي لـ v1) |
|---------|---------------------|
| GET | `/platform/registration-profiles` |
| POST | `/platform/registration-profiles/{id}/approve` |
| POST | `/platform/registration-profiles/{id}/reject` |
| POST | `/platform/registration-profiles/{id}/request-more-info` |
| POST | `/platform/registration-profiles/{id}/suspend` |

*(يُرجى التحقق من وسيطات `auth` و`permission` في الملف عند كل إصدار.)*

---

## هـ) إعدادات البيئة والإنتاج

| مصدر | مفاتيح/ملاحظات |
|--------|----------------|
| `backend/.env` + `config/saas.php` | Twilio أو مزود SMS، `PHONE_OTP_TTL`، حدود الإرسال/المحاولة |
| **إنتاج** | **عدم** تفعيل `PHONE_OTP_FAKE_PLAINTEXT` — لا إرجاع OTP في body للعميل |
| قاعدة البيانات | تشغيل **migrations** الجديدة (جدول `phone_otps`، `registration_profiles`، تعديلات `users`) |

تفاصيل إضافية في تعليقات `backend/.env.example` (قسم الجوال + OTP).

---

## و) قاعدة البيانات (Migrations)

| الملف (مسار نسبي من جذر المستودع) |
|-----------------------------------|
| `backend/database/migrations/2026_04_12_100000_alter_users_for_phone_registration.php` |
| `backend/database/migrations/2026_04_12_100001_create_phone_otps_table.php` |
| `backend/database/migrations/2026_04_12_100002_create_registration_profiles_table.php` |

---

## ز) ملفات الكود — مرجع المسارات (للمطابقة مع Git)

### Backend — خدمات ومسارات التسجيل بالجوال
- `backend/app/Services/PhoneRegistration/PhoneOtpService.php`
- `backend/app/Services/PhoneRegistration/RegisterOrLoginByPhoneService.php`
- `backend/app/Services/PhoneRegistration/CompleteRegistrationProfileService.php`
- `backend/app/Services/PhoneRegistration/PhoneRegistrationTokenIssuer.php`
- `backend/app/Services/PhoneRegistration/ApprovePhoneCompanyRegistrationService.php`
- `backend/app/Services/Auth/LoginBootstrapService.php` (حمولة `company_id` فارغ / `home_screen`)

### Backend — HTTP
- `backend/app/Http/Controllers/Api/V1/Auth/PhoneOtpAuthController.php`
- `backend/app/Http/Controllers/Api/V1/Auth/PhoneRegistrationFlowController.php`
- `backend/app/Http/Controllers/Api/V1/PlatformPhoneRegistrationReviewController.php`
- `backend/app/Http/Requests/Auth/PhoneOtpSendRequest.php`
- `backend/app/Http/Requests/Auth/PhoneOtpVerifyRequest.php`
- `backend/app/Http/Requests/Auth/CompleteAccountTypeRequest.php`
- `backend/app/Http/Requests/Auth/CompleteIndividualProfileRequest.php`
- `backend/app/Http/Requests/Auth/CompleteCompanyProfileRequest.php`

### Backend — نماذج وصلاحيات وتعديلات دخول
- `backend/app/Models/PhoneOtp.php`
- `backend/app/Models/RegistrationProfile.php`
- `backend/app/Models/User.php` (حقول/علاقات ذات صلة)
- `backend/app/Enums/UserRole.php`
- `backend/config/permissions.php`
- `backend/config/saas.php`
- `backend/routes/api.php`
- `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` (توافق مع `phone_onboarding`)

### Frontend — صفحات ومسار وحالة
- `frontend/src/views/auth/LoginView.vue`
- `frontend/src/views/auth/RegisterView.vue`
- `frontend/src/views/phone/*.vue` (مجلد كامل)
- `frontend/src/router/index.ts`
- `frontend/src/stores/auth.ts`
- `frontend/src/utils/phoneOnboardingRedirect.ts` + `phoneOnboardingRedirect.test.ts`
- `frontend/src/views/admin/AdminRegistrationQueueView.vue`
- `frontend/src/views/marketing/LandingView.vue` (شريط العودة لجلسة الجوال)
- `frontend/src/i18n/ar.ts`, `en.ts`, `ur.ts`, `hi.ts`, `tl.ts`, `bn.ts` (`login.linkPhoneOtp`)

### الاختبارات والـ CI
- `backend/tests/Feature/Auth/PhoneRegistrationFlowTest.php`
- `scripts/staging-gate.sh` — يشمل PHPUnit لملف التدفق أعلاه
- `Makefile` — هدف `staging-gate`
- `frontend/e2e/phone-onboarding-smoke.spec.ts`

### وثائق أخرى في المستودع (مرجع)
- `docs/Web_Login_Register_PhoneOtp_Release.md`
- `docs/PhoneRegistration_IndividualPolicy.md`

---

## ح) الاختبارات (ما يُشغَّل للثقة)

| الأداة | الأمر / الموقع |
|--------|----------------|
| PHPUnit (Docker) | من جذر المشروع: `docker compose exec -T app sh -lc "cd /var/www && php artisan config:clear && ./vendor/bin/phpunit tests/Feature/Auth/PhoneRegistrationFlowTest.php"` |
| بوابة staging | `bash scripts/staging-gate.sh` أو `make staging-gate` (يتطلب Docker) |
| Vitest | `frontend`: مسار `src/utils/phoneOnboardingRedirect.test.ts` ضمن `npm test` |
| Playwright | `frontend`: `npx playwright test e2e/phone-onboarding-smoke.spec.ts` |

---

## ط) خطوات الرفع والتحقق (قائمة عمل)

1. **سحب الإصدار** المعتمد من Git (الفرع/الوسم الذي يحتوي الميزة).
2. **Backend:** `php artisan migrate --force` على البيئة المستهدفة.
3. **ضبط `.env`:** مزود SMS، تعطيل أي وضع «fake OTP» في الإنتاج، مراجعة حدود `PHONE_OTP_*`.
4. **Frontend:** `npm ci` (أو ما يعادله) ثم `npm run build` ونشر مجلد البناء خلف nginx/Vite حسب معماريتكم.
5. **Smoke يدوي (ويب):**
   - `/login` — دخول حساب قديم (بريد + كلمة مرور).
   - من `/login` — فتح `/phone` وطلب رمز والتحقق (staging).
   - إكمال فرد حتى `/phone/onboarding/done`.
   - مسار شركة حتى `pending-review` وفتح `/admin/registration-profiles` بمستخدم منصة.
6. **مراقبة:** سجلات Laravel عند فشل SMS أو تجاوز الحدود؛ عدم تسريب وجود الرقم في رسائل الخطأ.

---

## ي) سياسة المستخدم الفرد (ملخص)

بعد إكمال الاسم: **هوية فقط** (`phone_onboarding`، بدون `company_id`) — شاشة إرشاد وربط لاحق بتسجيل شركة (`/register`) أو دخول حساب شركة (`/login`). التفاصيل في `docs/PhoneRegistration_IndividualPolicy.md` داخل المستودع.

---

## ك) جهة الاتصال عند الاستفسار

راجع مالك المستودع أو قائد الإصدار لربط هذا التسليم بوسم Git محدد (tag) لضمان تطابق الوثيقة مع الكود المُنشر.

---
*وثيقة تسليم — مسار الويب للجوال + OTP وتسجيل الدخول/التسجيل.*
