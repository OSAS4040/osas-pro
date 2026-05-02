<?php

declare(strict_types=1);
use App\Http\Controllers\Api\V1\SubscriptionsV2\PlatformSubscriptionsDebugHealthController;

return [
    /**
     * فحص صحة مؤقت لمسارات اشتراكات المنصة — يُفضّل إبقاؤه false في الإنتاج.
     *
     * @see PlatformSubscriptionsDebugHealthController
     */
    'debug_health_enabled' => (bool) env('PLATFORM_SUBSCRIPTIONS_DEBUG_HEALTH', false),
];
