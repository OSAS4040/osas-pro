import 'vue-router'

declare module 'vue-router' {
  interface RouteMeta {
    /** صلاحية Laravel مطلوبة — يُفحص عبر auth.hasPermission */
    requiresPermission?: string
    /** يكفي أحد الصلاحيات المذكورة */
    requiresAnyPermission?: string[]
    /** يجب امتلاك جميع الصلاحيات المذكورة */
    requiresAllPermissions?: string[]
    /** شاشة معاينة غير مفعّلة — يُعاد توجيهها لصفحة بعدم التوفر */
    unavailablePreview?: boolean
    /** ميزة غير مفعّلة في الواجهة الحالية — صفحة توضيحية دون إخفاء الملف */
    featureInactive?: boolean
    /** ذكاء الأعمال (BI) — يتطابق مع `sectionEnabled('intelligence')` + علم البناء */
    staffIntelligenceBi?: boolean
    /** مفتاح في `effective_feature_matrix` للمنشأة — غير المالك يُمنع عند التعطيل */
    requiresBusinessFeature?: string
    intelligenceCommandCenter?: boolean
    /** الأرشفة الإلكترونية — يُفعّل/يُعطّل بـ VITE_ELECTRONIC_ARCHIVE */
    electronicArchive?: boolean
    /** صفحة ضيف مخصّصة — يُعاد توجيه المالك المسجّل إلى /admin */
    platformAdminLogin?: boolean
    /** لوحة مشغّل المنصة — حساب بلا شركة + principal_kind platform_employee فقط */
    requiresPlatformAdmin?: boolean
    /** عنوان شاشة وحدات التسعير/المزودين (Placeholder يقرأ من meta) */
    platformPricingTitle?: string
    platformPricingHint?: string
  }
}
