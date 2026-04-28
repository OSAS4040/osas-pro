<?php

declare(strict_types=1);

namespace App\Jobs\SubscriptionsV2;

use App\Modules\SubscriptionsV2\Models\RealtimeEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class PruneSubscriptionRealtimeEventsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $batchSize = 5000,
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $retention = (int) env('SUBSCRIPTIONS_REALTIME_RETENTION_DAYS', 14);
        if ($retention < 1) {
            Log::warning('subscriptions.realtime.pruned_skipped', [
                'reason' => 'invalid_retention_days',
                'retention_days' => $retention,
            ]);

            return;
        }
        $cutoff = now()->subDays($retention);
        $batchSize = max(100, $this->batchSize);
        $totalDeleted = 0;

        do {
            $ids = RealtimeEvent::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = RealtimeEvent::query()->whereIn('id', $ids->all())->delete();
            $totalDeleted += $deleted;
        } while (true);

        Log::info('subscriptions.realtime.pruned', [
            'deleted' => $totalDeleted,
            'retention_days' => $retention,
            'batch_size' => $batchSize,
        ]);

        $remaining = RealtimeEvent::query()
            ->where('created_at', '<', $cutoff)
            ->count();

        Log::info('subscriptions.realtime.backlog', [
            'remaining' => $remaining,
            'retention_days' => $retention,
        ]);
    }
}

