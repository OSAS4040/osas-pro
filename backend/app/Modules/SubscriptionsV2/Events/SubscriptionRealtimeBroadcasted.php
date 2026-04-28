<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class SubscriptionRealtimeBroadcasted implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $eventType,
        public readonly ?int $companyId,
        public readonly string $audience,
        public readonly array $payload,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        if ($this->audience === 'admin') {
            return new PrivateChannel('private-admin');
        }

        return new PrivateChannel('private-company-'.$this->companyId);
    }

    public function broadcastAs(): string
    {
        return 'subscriptions.realtime';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->eventType,
            'company_id' => $this->companyId,
            'payload' => $this->payload,
            'sent_at' => now()->toIso8601String(),
        ];
    }
}

