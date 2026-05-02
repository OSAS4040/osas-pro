<?php

declare(strict_types=1);

return [
    /**
     * عند التفعيل: إذا وُجدت نسخة أسعار مرجعية معتمدة من المنصّة (`platform_customer_price_versions`)
     * وتتضمّن sell_snapshot سطراً يطابق service.code للخدمة، يُستخدم سعر الوحدة منها
     * قبل سياسات التسعير tenant وسعر الخدمة الأساسي (ولا يطغى على بند عقد فعّال).
     */
    'platform_catalog_resolver' => [
        'enabled' => env('PRICING_PLATFORM_SNAPSHOT_RESOLVER_ENABLED', true),
    ],
];
