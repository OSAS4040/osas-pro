<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchType;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use App\Modules\SubscriptionsV2\Services\ReconciliationService;
use Illuminate\Support\Facades\DB;

final class ManualMatchAction
{
    public function __construct(
        private readonly ReconciliationService $reconciliationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(int $paymentOrderId, int $bankTransactionId, int $adminUserId): void
    {
        DB::transaction(function () use ($paymentOrderId, $bankTransactionId, $adminUserId): void {
            $order = PaymentOrder::query()->whereKey($paymentOrderId)->lockForUpdate()->firstOrFail();
            $tx     = BankTransaction::query()->whereKey($bankTransactionId)->lockForUpdate()->firstOrFail();

            $score = $this->reconciliationService->scoreMatch($order, $tx);

            $this->reconciliationService->confirmMatch(
                $order,
                $tx,
                ReconciliationMatchType::Manual,
                $adminUserId,
                $score,
                'manual_match',
            );

            $this->auditLogService->log(
                $adminUserId,
                'manual_match',
                'PaymentOrder',
                $order->id,
                null,
                ['bank_transaction_id' => $tx->id, 'score' => (string) $score],
                [],
            );
        });
    }
}
