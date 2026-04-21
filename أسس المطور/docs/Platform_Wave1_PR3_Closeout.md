# WAVE 1 / PR3 — Closeout: Session Visibility, Device Tracking, Login Audit

**التاريخ:** 2026-04-12  
**النتيجة:** PASS (مشروط بتشغيل PHPUnit كامل في بيئة تملك PHP، وإغلاق PR1+PR2 بعد دفعة الاختبارات المطلوبة)

---

## 1) ملخص تنفيذي

- توسيع **`personal_access_tokens`** ببيانات وصفية خفيفة: `auth_channel`, `ip_address`, `user_agent`, `user_agent_summary` عبر نموذج **`AuthPersonalAccessToken`** و`Sanctum::usePersonalAccessTokenModel`.
- جدول **`auth_login_events`** (سجل تدقيق append-only) مع **`AuthLoginEventRecorder`**: `login_success`, `login_denied`, `logout_session`, `logout_all`, `revoke_session`, `revoke_other_sessions`.
- **`AuthSessionMetadataWriter`** يكتب الوصفية بعد إصدار التوكن (كلمة مرور، تسجيل، مسارات الهاتف/OTP حسب التوصيل السابق).
- **`AuthSessionController`**: `GET /api/v1/auth/sessions`, `DELETE /api/v1/auth/sessions/{id}` (لا يلغي الجلسة الحالية — يُرجع 422 ويُفضّل `POST /auth/logout`), `POST /api/v1/auth/sessions/revoke-others`.
- واجهة **`AuthSessionsView`** + مسار **`/account/sessions`** + عنصر تنقّل للموظفين (`AppLayout`) + بحث القائمة السريعة.
- عند فشل كلمة المرور: تسجيل **`login_denied`** مع `reason_code = invalid_credentials` و`user_id` فارغ (تجنب ربط خاطئ عند بريد مكرر).

---

## 2) الملفات المعدّلة / المضافة

| الملف |
|-------|
| `backend/database/migrations/2026_04_12_200000_extend_personal_access_tokens_for_session_tracking.php` |
| `backend/database/migrations/2026_04_12_200001_create_auth_login_events_table.php` |
| `backend/app/Models/AuthPersonalAccessToken.php` |
| `backend/app/Models/AuthLoginEvent.php` |
| `backend/app/Providers/AppServiceProvider.php` |
| `backend/app/Support/Auth/UserAgentSummarizer.php` |
| `backend/app/Support/Auth/IpAddressSummarizer.php` |
| `backend/app/Services/Auth/AuthSessionMetadataWriter.php` |
| `backend/app/Services/Auth/AuthLoginEventRecorder.php` |
| `backend/app/Http/Controllers/Api/V1/Auth/AuthSessionController.php` |
| `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php` |
| `backend/app/Http/Controllers/Api/V1/Auth/PhoneOtpAuthController.php` *(رفض أهلية / تدقيق حسب التسليم)* |
| `backend/routes/api.php` |
| `backend/tests/Feature/Auth/AuthSessionsTest.php` |
| `frontend/src/views/account/AuthSessionsView.vue` |
| `frontend/src/router/index.ts` |
| `frontend/src/layouts/AppLayout.vue` |
| `frontend/src/config/navSearchItems.ts` |
| `docs/Platform_Wave1_PR3_Closeout.md` |

---

## 3) Migrations

**نعم.**

1. أعمدة وصفية على **`personal_access_tokens`**.
2. إنشاء **`auth_login_events`**.

---

## 4) ما الذي تغيّر وظيفياً

- المستخدم يستطيع عرض جلساته النشطة (من جدول التوكن) مع تمييز **الجلسة الحالية** وملخص وكيل/IP.
- إلغاء جلسة أخرى، أو إلغاء جميع الجلسات الأخرى، مع سجل أحداث.
- تسجيل أوضح لنجاح/رفض الدخول وتسجيل الخروج (حسب المسارات المربوطة بالمسجّل).

---

## 5) ما الذي لم يُلمس

- محرك suspicious login كامل، geo-risk، device trust معقّد، impersonation، 2FA، قيود IP، تغييرات مالية أو `customers`، إعادة تصميم تدفق الدخول بالكامل، توحيد مسارات legacy.

---

## 6) الاختبارات المنفذة / الموصى بها

```bash
php artisan test --filter=ResolveLoginEligibilityAction
php artisan test --filter=LoginEligibility
php artisan test --filter=ResolveLoginContextAction
php artisan test --filter=LoginAccountContext
php artisan test tests/Feature/Auth/LoginTest.php
php artisan test tests/Feature/Auth/PhoneRegistrationFlowTest.php
php artisan test tests/Feature/Auth/AuthApiContractTest.php
php artisan test --filter=AuthSessionsTest
```

---

## 7) PASS / FAIL

**PASS** (منطقياً بعد `AuthSessionsTest` في بيئة التطوير) — **مشروط** بتشغيل دفعة PHPUnit الكاملة لـ PR1+PR2 كما طُلب قبل الإغلاق النهائي.

---

## 8) المخاطر المتبقية

- **`SANCTUM_UPDATE_LAST_USED_AT`**: إن بقي معطّل، قد يظل **`last_used_at`** فارغاً؛ الواجهة تعرض **`created_at`** كبديل.
- تسجيل **`login_denied`** عند بيانات خاطئة **بدون `user_id`** لتقليل خطأ الإسناد عند البريد المشترك بين شركات.

---

## 9) GO / NO-GO لـ PR4

**GO** — بعد اجتياز قائمة الاختبارات أعلاه في بيئتكم ودمج الترحيلات.
