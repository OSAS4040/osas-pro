# تقرير تقدم المرحلة 6 — ذكاء المنصة والإشارات

**حالة الوثيقة:** **تقدم** — سطح ذكاء المنصة ووحدات الارتباط والحوادث في تطور نشط.

## 1. الهدف

حماية نقاط القراءة الرسمية للإشارات والصلاحيات المرتبطة بها، مع الحفاظ على وجود `trace_id` وهيكل الاستجابة المتفق عليه.

## 2. قائمة تحقق

| البند | الملاحظات |
|--------|------------|
| إشارات، مرشحون، حوادث، سجل قرارات، إجراءات مضبوطة، إشعارات، سير عمل موجّه | اختبارات `tests/Feature/Platform/*` الموسومة `phase6` |
| دمج صلاحيات تسجيل الدخول للمشغّلين | `PlatformAuthPermissionsMergeTest` |
| عزل وصول المستأجر عن مسارات الإدارة | `PlatformAdminAccessIsolationTest`، `PlatformAdminKillSwitchTest` |
| واجهة (Vitest) لعقود الذكاء والمسارات والإشعارات وعقد الـ router | `npm run test:phase6` — مجلدات `types/platform-admin`, `composables/platform-admin`, `components/platform-admin`, وملفات router المنصة و`platformViewsLoad` |

## 3. الاختبارات

- مجموعة PHPUnit: **`phase6`**
- الأمر: `composer test:phase6`
- واجهة: `cd frontend && npm run test:phase6` (أنواع وcomposables ذكاء المنصة + حارس المسارات)

## 4. فجوات

- توسيع `test:phase6` عند إضافة وحدات Vitest جديدة تحت `platform-admin` خارج المجلدات الحالية.
