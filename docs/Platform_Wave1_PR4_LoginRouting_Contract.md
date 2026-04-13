# WAVE 1 / PR4 — Login routing & UI contract (frontend)

**التاريخ:** 2026-04-12

## 1) مصدر الحقيقة للتوجيه بعد الدخول

1. **`redirect` query** — يُقبل فقط إذا مرّ عبر `sanitizeInternalPath` (مسار داخلي بسيط، بدون `//` أو `..` أو مخطط URL) **و** وافق `isPathConsistentWithAccountContext` مع **`account_context`** الحالي (لا يُسمح لمستخدم ورشة بالانتقال إلى `/admin`، إلخ).
2. **`registrationFlow`** — عند `onboarding_active` يُستخدم `resolvePhoneOnboardingPath` (بدون تخطي خطوات المنتج).
3. **`account_context.home_route_hint`** — من الـ API بعد التحقق والتطهير كما في (1) والتوافق مع (2).
4. **`portalHomeFromRole`** — احتياطي نهائي من دور المستخدم في الواجهة (`auth.portalHome`).

## 2) مصدر الحقيقة لرسائل الخطأ

- **401 / 403 (وأجزاء من OTP):** الواجهة تعرض النص عبر **`message_key` → مفتاح i18n `login.apiErrors.*`** عندما يكون المفتاح معروفاً.
- حقل **`message`** من الخادم يُستخدم كعرض احتياطي فقط عند غياب `message_key` أو عدم تطابقه مع خريطة الواجهة.
- **لا يُبنى منطق القرار على مطابقة نص عربي/إنجليزي ثابت** في الكود (إزالة الاعتماد على سلاسل مثل «بيانات الدخول غير صحيحة» للفرع المنطقي).

## 3) `account_context` (حقول مستخدمة في الواجهة)

| الحقل | الاستخدام |
|--------|------------|
| `principal_kind` | تصنيف عالٍ: `platform_employee`, `tenant_user`, `customer_user`, `unknown` |
| `guard_hint` | `platform`, `staff`, `fleet`, `customer`, `onboarding`, `unknown` |
| `home_route_hint` | اقتراح المسار الافتراضي من الخادم (بعد التحقق من الصلاحية في الواجهة) |
| `user_id`, `company_id`, `customer_id` | عرض/سياق لاحق؛ التوجيه الحالي لا يعتمد على نصوص |

## 4) `/auth/me`

- يعيد **`account_context`** عندما تسمح الأهلية والسياق (مثل بعد تسجيل الدخول)، ليتم مزامنة التوجيه بعد تحديث الصفحة أو `fetchMe`.

## 5) التسجيل `POST /auth/register`

- يعيد **`account_context`** للمالك الجديد لتطبيق نفس قواعد التوجيه بعد إنشاء الحساب.

## 6) ما لم يُلمس

- محركات أمان متقدمة، 2FA، impersonation، نواة مالية، توحيد مسارات legacy بالكامل.
