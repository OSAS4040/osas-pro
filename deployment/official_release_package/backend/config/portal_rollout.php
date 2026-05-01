<?php

declare(strict_types=1);

/**
 * مرجع إطلاق بوابة العميل/الأسطول والتقارير المالية الموحدة.
 * القيم هنا للتوثيق والمراجعة التشغيلية؛ منطق الحماية الفعلي في الخدمات والـ middleware.
 */
return [

    'pre_release_checklist' => [
        'التأكد من وجود نسخة أسعار مرجعية معتمدة من المنصة (PlatformCustomerPriceVersion + طلب Approved) لكل عميل B2B قبل السماح بإنشاء أوامر عمل من حسابات العميل/الأسطول.',
        'مراجعة تقارير المبيعات ولوحات المؤشرات: أرقام الإيرادات تستبعد فواتير التسوية الداخلية billing_flow_type=provider_to_platform لتفادي الازدواج مع فاتورة العميل.',
        'التحقق من إخفاء فواتير المزوّد→المنصة عن بوابة العميل والمسارات المحمية في InvoiceController.',
        'واجهة: مراجعة feature flags في frontend/src/config/featureFlags.ts وإصدارات VITE_* في CI.',
        'الإنتاج: PLATFORM_ADMIN_ENABLED، حدود المعدل، ونسخ احتياطي/خطة تراجع قبل توسيع الجمهور.',
    ],

    /**
     * يمكن ربطها لاحقاً بـ middleware اختياري؛ حالياً البوابات مفعّلة في الكود.
     */
    'strict_platform_pricing_gate' => (bool) env('PORTAL_STRICT_PLATFORM_PRICING_GATE', true),
];
