# WAVE 1 / PR4 — Closeout: Login UX & Account Context Routing

**التاريخ:** 2026-04-12  
**النتيجة:** PASS (بعد تشغيل التحقق المذكور في القسم 6 أدناه في بيئة التطوير)

---

## 1) ملخص تنفيذي

- **عقد التوجيه بعد الدخول:** طبقة موحّدة في الواجهة (`sanitizeInternalPath`، `isPathConsistentWithAccountContext`، `resolvePostLoginTarget`) تربط **`account_context`** القادم من الـ API بمسار Vue آمن، مع أولوية: `redirect` query (إن وُجد وصالح) → تدفق **`registrationFlow` / الهاتف** → **`home_route_hint`** → **`portalHome`** من الدور.
- **تخزين السياق:** الـ store يحتفظ بـ **`accountContext`** بعد `login` / `register` / `verify-otp` ويُحدَّث من **`GET /auth/me`** عند التحميل.
- **رسائل الخطأ:** اعتماد **`message_key`** (ومنها `auth.login.invalid_credentials`) مع ترجمة **`login.apiErrors.*`**؛ **`message`** للعرض الاحتياطي فقط وليس للمنطق.
- **الخلفية:** إرجاع **`account_context`** مع **`POST /auth/register`** ومع **`GET /auth/me`** عند السماح؛ وإضافة **`reason_code` + `message_key`** لاستجابة **401** عند كلمة مرور خاطئة.
- **الواجهات المحدّثة:** `LoginView`، `PlatformAdminLoginView`، `PhoneOtpVerifyView`، `RegisterView` مع تحسين حالات التحميل/الخطأ لصفحة التحقق بالهاتف.
- **التوثيق:** `docs/Platform_Wave1_PR4_LoginRouting_Contract.md` يصف عقد التوجيه ورسائل الخطأ.

---

## 2) الملفات المعدّلة / المضافة

| الملف |
|-------|
| `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` |
| `backend/config/auth_login_eligibility.php` |
| `backend/tests/Feature/Auth/AuthApiContractTest.php` |
| `backend/tests/Feature/Auth/LoginTest.php` |
| `frontend/src/types/accountContext.ts` *(جديد)* |
| `frontend/src/utils/postLoginRedirect.ts` *(جديد)* |
| `frontend/src/utils/postLoginRedirect.test.ts` *(جديد)* |
| `frontend/src/utils/loginApiErrors.ts` *(جديد)* |
| `frontend/src/utils/loginApiErrors.test.ts` *(جديد)* |
| `frontend/src/stores/auth.ts` |
| `frontend/src/views/auth/LoginView.vue` |
| `frontend/src/views/auth/PlatformAdminLoginView.vue` |
| `frontend/src/views/auth/RegisterView.vue` |
| `frontend/src/views/phone/PhoneOtpVerifyView.vue` |
| `frontend/src/i18n/ar.ts` |
| `frontend/src/i18n/en.ts` |
| `frontend/src/i18n/bn.ts` |
| `frontend/src/i18n/hi.ts` |
| `frontend/src/i18n/tl.ts` |
| `frontend/src/i18n/ur.ts` |
| `docs/Platform_Wave1_PR4_LoginRouting_Contract.md` |
| `docs/Platform_Wave1_PR4_Closeout.md` |

---

## 3) Migrations

**لا.** (تغييرات PR4 على إعدادات/نصوص واستجابات API وواجهة فقط.)

---

## 4) ما الذي تغيّر وظيفياً

- بعد **تسجيل الدخول / التسجيل / التحقق من OTP** يُحسب مسار الانتقال من **`account_context`** وبقيود بوابة/دور، مع دعم **`redirect`** الداخلي الآمن.
- **`/auth/me`** يعيد **`account_context`** عند السماح لمزامنة الواجهة بعد التحديث.
- **401** بكلمة مرور خاطئة يتضمن **`INVALID_CREDENTIALS`** و**`auth.login.invalid_credentials`** مع نص من الإعدادات حسب لغة التطبيق في الخادم.
- **403** أهلية الدخول: الواجهة تعرض نصاً مبنياً على **`message_key`** عند الإمكان.

---

## 5) ما الذي لم يُلمس

- PR5 (Rate limiting / suspicious signals)، النواة المالية، `customers`، impersonation، 2FA، geo engine، توحيد مسارات legacy بالكامل.

---

## 6) عقد التوجيه وربط `account_context` (تفاصيل)

- **`account_context`:** يطابق `LoginAccountContext::toArray()` (principal_kind، guard_hint، home_route_hint، معرّفات الكيان، display_context).
- **`sanitizeInternalPath`:** يقبل مسارات داخلية فقط (`/...`) بدون `//` أو `..` أو مخطط URL.
- **`isPathConsistentWithAccountContext`:** يمنع مثلاً توجيه **`tenant_user`** إلى **`/admin`**، أو **`platform_employee`** خارج **`/admin`**، ويربط **`onboarding`** بمسار **`/phone/onboarding/**`**.
- **`resolvePostLoginTarget`:** يطبّق ترتيب الأولويات في العقد؛ **`PhoneOtpVerifyView`** يستدعيه بعد **`hydrateFromPhoneVerifyResponse`** و`fetchRegistrationFlow`.
- **`LoginView`:** يستدعي **`fetchRegistrationFlow`** عند **`phone_onboarding`** قبل حل المسار لضمان خطوات الـ onboarding.

المرجع الكامل: **`docs/Platform_Wave1_PR4_LoginRouting_Contract.md`**.

---

## 7) الاختبارات المنفذة فعلياً (عند إغلاق PR4)

### Vitest (مساعدات التوجيه ورسائل API)

```bash
cd frontend
npm run test -- --run src/utils/postLoginRedirect.test.ts src/utils/loginApiErrors.test.ts
```

### vue-tsc

```bash
cd frontend
npm run type-check
```

### Laravel (Docker — عقد الدخول / account_context / me / register + LoginTest كاملاً)

```bash
docker compose exec -T app php artisan test tests/Feature/Auth/AuthApiContractTest.php tests/Feature/Auth/LoginAccountContextTest.php tests/Feature/Auth/LoginTest.php
```

*(اختبار كلمة المرور الخاطئة يعتمد على `message_key` / `reason_code` وليس على نص `message` ثابت، ليتوافق مع لغة تطبيق الاختبار.)*

### E2E (Playwright — تدفق ضيف أساسي للدخول بدون الاعتماد على API)

```bash
cd frontend
npx playwright test e2e/auth-public.spec.ts
```

*(المواصفات تتحقق من ظهور حقول الدخول وروابط الصفحة و404؛ لا تُجري تسجيل دخول حقيقياً عبر API.)*

### نتائج التشغيل عند إعداد هذا الإغلاق

| الحزمة | النتيجة |
|--------|---------|
| Vitest (`postLoginRedirect` + `loginApiErrors`) | **11 passed** |
| `vue-tsc --noEmit` | **PASS** |
| Playwright `e2e/auth-public.spec.ts` | **5 passed** |
| PHPUnit (Docker): `AuthApiContractTest` + `LoginAccountContextTest` + `LoginTest` | **26 passed** |

---

## 8) ما الذي لم يُشغَّل في إغلاق PR4

- **Playwright كامل** (`npm run test:e2e`) أو مسارات إنتاج ثقيلة.
- **دفعة PHPUnit الكاملة** لـ WAVE 1 (PR1–PR3 + PR4) في جلسة واحدة — يُنصح بتشغيلها قبل الدمج إلى `main` إن لم تُنفَّذ بعد آخر تغيير.

---

## 9) PASS / FAIL

**PASS** — وفق جدول «نتائج التشغيل» في القسم 7 أعلاه.

---

## 10) المخاطر المتبقية

- **`redirect` query:** مُقيّد بشكل صارم؛ روابط عميقة نادرة قد تُرفض إن لم تمر بالمطابقة مع **`account_context`**.
- **`/auth/me`:** يعتمد على **`ResolveLoginContextAction`**؛ إن تغيّرت قواعد الأهلية لاحقاً يجب مراجعة توافق استجابة **`account_context`** مع الواجهة.

---

## 11) GO / NO-GO لـ PR5

**GO لـ PR5** — بعد اعتماد هذا الإغلاق وتثبيت نتائج التحقق في بيئة الدمج.

**لا يبدأ PR5** قبل اعتماد هذا المستند ونجاح التحقق الأدنى في القسم 7 في بيئتكم.
