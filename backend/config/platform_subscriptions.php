<?php

declare(strict_types=1);

return [
    /**
     * فحص صحة مؤقت لمسارات اشتراكات المنصة — يُفضّل إبقاؤه false في الإنتاج.
     *
     * @see App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformSubscriptionsDebugHealthController
     */
    'debug_health_enabled' => (bool) env('PLATFORM_SUBSCRIPTIONS_DEBUG_HEALTH', false),
];
