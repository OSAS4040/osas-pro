<?php

namespace App\Jobs;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckSubscriptionStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Subscription::where('status', SubscriptionStatus::Active)
            ->where('ends_at', '<', now())
            ->each(function (Subscription $subscription) {
                $subscription->update([
                    'status'        => SubscriptionStatus::GracePeriod,
                    'grace_ends_at' => now()->addDays(15),
                ]);
            });

        Subscription::where('status', SubscriptionStatus::GracePeriod)
            ->where('grace_ends_at', '<', now())
            ->each(function (Subscription $subscription) {
                $subscription->update(['status' => SubscriptionStatus::Suspended]);
            });
    }
}
