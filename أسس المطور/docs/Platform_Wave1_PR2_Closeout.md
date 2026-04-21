# WAVE 1 / PR2 — Closeout: Unified Login Resolver & Account Context

**التاريخ:** 2026-04-12  
**النتيجة:** PASS (مشروط بتشغيل PHPUnit في بيئة تملك PHP/Docker)

---

## 1) ملخص تنفيذي

- إضافة **`ResolveLoginContextAction`**: يستدعي **`ResolveLoginEligibilityAction`** أولاً (بدون تكرار قواعد الأهلية)، ثم يبني **`LoginAccountContext`** عند السماح.
- عقد ثابت للواجهة: `principal_kind`، `guard_hint`، `home_route_hint`، `user_id`، `company_id`، `customer_id`، `role`، `requires_context_selection`، `display_context`.
- **`AuthController`**: يخزّن `LoginContextResolution` في `Request` attributes بعد نجاح أهلية+سياق المستخدم، ويُلحق **`account_context`** في استجابة إصدار التوكن.
- **`PhoneOtpAuthController`**: نفس السلسلة؛ يُلحق `account_context` بعد نجاح OTP عند السماح.
- **`GlobalTenantGuardMiddleware`**: يبقى على **`ResolveLoginEligibilityAction`** فقط (403 خفيف بدون تضخيم الحمولة).

### سياسة العقد (رسائل vs reason_code)

- **الواجهات يجب أن تعتمد على `reason_code` و `message_key` وحقول `account_context`** للمنطق.
- حقل **`message`** يبقى للعرض البشري فقط؛ **لا coupling على مطابقة نص ثابت** في الـ SPA إن أمكن.

---

## 2) الملفات المعدّلة / المضافة

| الملف |
|-------|
| `backend/app/Enums/LoginPrincipalKind.php` *(جديد)* |
| `backend/app/Enums/LoginGuardHint.php` *(جديد)* |
| `backend/app/Support/Auth/LoginAccountContext.php` *(جديد)* |
| `backend/app/Support/Auth/LoginContextResolution.php` *(جديد)* |
| `backend/app/Actions/Auth/ResolveLoginContextAction.php` *(جديد)* |
| `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` |
| `backend/app/Http/Controllers/Api/V1/Auth/PhoneOtpAuthController.php` |
| `backend/tests/Unit/Auth/ResolveLoginContextActionTest.php` *(جديد)* |
| `backend/tests/Feature/Auth/LoginAccountContextTest.php` *(جديد)* |
| `backend/tests/Feature/Auth/AuthApiContractTest.php` |
| `backend/tests/Feature/Auth/PhoneRegistrationFlowTest.php` |
| `docs/Platform_Wave1_PR2_Closeout.md` |

---

## 3) Migrations

**لا.**

---

## 4) ما الذي تغيّر وظيفياً

- استجابة **`POST /api/v1/auth/login`** الناجحة تتضمن **`account_context`** (إن وُجدت دقة سياق؛ عند فشل الأهلية لا يُصدر توكن كما في PR1).
- استجابة **`POST /api/v1/auth/phone/verify-otp`** الناجحة تتضمن **`account_context`**.
- تصنيف **`principal_kind`**: `platform_employee` (بريد في `config('saas.platform_admin_emails')`)، `tenant_user` (ورشة)، `customer_user` (أسطول/عميل)، `unknown` (تسجيل هاتف بدون شركة أو حالات غير مصنّفة).
- **`home_route_hint`**: مسارات Vue تقريبية (`/admin`, `/`, `/fleet-portal`, `/customer/dashboard`, `/phone/onboarding`) مبنية على **role + company + customer_id** وليس على نصوص الواجهة.

---

## 5) ما الذي لم يُلمس

- النواة المالية، المحافظ، القيود، `customers` structure، uniqueness، توحيد REST القديم.
- device/session tracking كامل، suspicious login، impersonation، إعادة تصميم UX.

---

## 6) الاختبارات الموصى بها

```bash
php artisan test --filter=ResolveLoginContextAction
php artisan test --filter=LoginAccountContext
php artisan test tests/Feature/Auth/LoginEligibilityTest.php
php artisan test tests/Feature/Auth/PhoneRegistrationFlowTest.php
php artisan test tests/Feature/Auth/AuthApiContractTest.php
```

---

## 7) PASS / FAIL

**PASS** (منطقياً) — **مشروط** بتشغيل PHPUnit في بيئتكم.

---

## 8) المخاطر المتبقية

- **`home_route_hint`** تقريبي؛ يجب مواءمته مع تطور الـ Vue router.
- مالك شركة ببريد مسجّل كمنصة يُصنَّف `platform_employee` حتى داخل شركة — مقصود لسياسة `SAAS_PLATFORM_ADMIN_EMAILS`.

---

## 9) GO / NO-GO لـ PR3

**GO** — PR3 يمكن أن يوسّع **التوجيه الشرطي** أو **requires_context_selection** عند ظهور تعدد سياق حقيقي، مع الإبقاء على **`ResolveLoginContextAction`** كمصدر واحد.
