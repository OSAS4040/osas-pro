<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Modules\SubscriptionsV2\Events\SubscriptionRealtimeBroadcasted;
use App\Modules\SubscriptionsV2\Models\RealtimeEvent;

final class RealtimeNotificationService
{
    /**
     * @param array<string, mixed> $payload
     */
    public function publish(string $eventType, ?int $companyId, string $audience, array $payload): void
    {
        $record = RealtimeEvent::query()->create([
            'company_id' => $companyId,
            'audience' => $audience,
            'event_type' => $eventType,
            'payload' => $payload,
        ]);

        event(new SubscriptionRealtimeBroadcasted(
            eventType: $eventType,
            companyId: $companyId,
            audience: $audience,
            payload: array_merge($payload, ['event_id' => $record->id]),
        ));
    }
}

