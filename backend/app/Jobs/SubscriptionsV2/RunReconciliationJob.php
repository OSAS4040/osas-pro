<?php

declare(strict_types=1);

namespace App\Jobs\SubscriptionsV2;

use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\ReconciliationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class RunReconciliationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $paymentOrderId,
    ) {
        $this->onQueue('high');
    }

    public function handle(ReconciliationService $reconciliationService): void
    {
        $order = PaymentOrder::query()->find($this->paymentOrderId);
        if ($order === null) {
            return;
        }

        $reconciliationService->autoMatch($order);
    }
}
