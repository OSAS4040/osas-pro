<?php

declare(strict_types=1);

namespace App\Jobs\SubscriptionsV2;

use App\Modules\SubscriptionsV2\Services\SubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunSubscriptionRenewalJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('high');
    }

    public function handle(SubscriptionService $subscriptionService): void
    {
        $subscriptionService->processRenewalCycle();
        $subscriptionService->progressLifecycleStates();
        $subscriptionService->applyScheduledDowngrades();
    }
}

