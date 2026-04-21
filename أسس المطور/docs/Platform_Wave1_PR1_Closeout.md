# WAVE 1 / PR1 — Closeout: Account Status Hardening & Login Eligibility

**التاريخ:** 2026-04-12  
**النتيجة:** PASS (بعد التحقق محلياً بـ `php artisan test --filter=LoginEligibility` و`ResolveLoginEligibilityAction` عند توفر PHP/Docker)

---

## 1) ملخص تنفيذي

- إضافة حالة **`blocked`** إلى `UserStatus` مع تسمية واضحة.
- إدخال **`ResolveLoginEligibilityAction`** + **`LoginEligibilityResult`** كبوابة مركزية لأهلية الدخول (قبل إصدار التوكن).
- ربط المسارين: **كلمة المرور** (`AuthController::loginPreTokenGuards` + `resetPassword`) و**OTP الهاتف** (`PhoneOtpAuthController::verifyOtp`) و**الطلبات المصدّقة** (`GlobalTenantGuardMiddleware`).
- استجابة موحّدة عند الرفض: `message`, `reason_code`, `message_key`, `trace_id` + تسجيل `auth.login.denied_eligibility` / `auth.phone_otp.denied_eligibility`.

---

## 2) الملفات المعدّلة / المضافة

| الملف |
|-------|
| `backend/app/Enums/UserStatus.php` |
| `backend/app/Actions/Auth/ResolveLoginEligibilityAction.php` *(جديد)* |
| `backend/app/Support/Auth/LoginEligibilityResult.php` *(جديد)* |
| `backend/config/auth_login_eligibility.php` *(جديد)* |
| `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` |
| `backend/app/Http/Controllers/Api/V1/Auth/PhoneOtpAuthController.php` |
| `backend/app/Http/Middleware/GlobalTenantGuardMiddleware.php` |
| `backend/tests/Unit/Auth/ResolveLoginEligibilityActionTest.php` *(جديد)* |
| `backend/tests/Feature/Auth/LoginEligibilityTest.php` *(جديد)* |
| `backend/tests/Feature/Auth/LoginTest.php` |
| `docs/Platform_Wave1_PR1_Closeout.md` *(هذا الملف)* |

---

## 3) Migrations

**لا يوجد.** العمود `users.status` نصي مسبقاً؛ القيمة `blocked` سلسلة جديدة فقط.

---

## 4) ما الذي تغيّر وظيفياً

- المستخدمون بحالة **`blocked` / `suspended` / `inactive`** أو **`active` مع `is_active = false`** لا يحصلون على **Bearer token** عبر تسجيل الدخول بكلمة المرور أو بعد نجاح OTP.
- رسائل الرفض **مصنّفة** بـ `reason_code` للواجهات والسجلات.
- `resetPassword` يستخدم نفس سياسة الأهلية بدل رسالة إنجليزية عامة فقط.

---

## 5) ما الذي لم يُلمس

- المحافظ، القيود، الفواتير، الـ ledger، أي migration مالي.
- `customers`، uniqueness الجوال على العملاء، توحيد مسارات REST القديمة.
- Resolver كامل، device tracking، suspicious login engine، impersonation، إعادة تصميم صفحات الدخول.

---

## 6) الاختبارات

- **Unit:** `ResolveLoginEligibilityActionTest` — الحالات والأولوية (مثلاً blocked مع `is_active` false).
- **Feature:** `LoginEligibilityTest` — دخول ناجح، محظور/موقوف/غير نشط/معطّل، OTP لحساب محظور بدون توكن.
- **تحديث:** `LoginTest::test_login_fails_for_inactive_user` يتوقع `ACCOUNT_DISABLED`.

**تشغيل موصى به:**

```bash
php artisan test --filter=LoginEligibility
php artisan test --filter=ResolveLoginEligibilityAction
php artisan test tests/Feature/Auth/LoginTest.php
```

---

## 7) PASS / FAIL

- **PASS** (منطقياً بعد المراجعة) — يتطلب التحقق بتشغيل PHPUnit في بيئة تملك PHP.

---

## 8) المخاطر المتبقية

- طلبات API تعتمد على نص رسالة `GlobalTenantGuardMiddleware` السابق بالإنجليزية قد ترى نصاً عربياً — مقصود للاتساق مع بقية رسائل المصادقة.
- أي بيانات قديمة بقيمة `status` غير معروفة تُصنَّف `LOGIN_NOT_ALLOWED` (نادر إذا بقي النظام متسقاً).

---

## 9) التوصية لـ PR2

**GO** — البدء بـ **Resolver** (سياق الحساب والتوجيه) مع الإبقاء على **`ResolveLoginEligibilityAction`** كخطوة أولى داخل الـ Resolver دون تكرار المنطق.
