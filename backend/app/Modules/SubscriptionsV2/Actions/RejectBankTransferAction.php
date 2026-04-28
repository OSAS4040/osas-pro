<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Enums\BankTransferReviewStatus;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use Illuminate\Support\Facades\DB;

final class RejectBankTransferAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(int $paymentOrderId, int $adminUserId, string $reason): PaymentOrder
    {
        return DB::transaction(function () use ($paymentOrderId, $adminUserId, $reason): PaymentOrder {
            $order = PaymentOrder::query()->whereKey($paymentOrderId)->lockForUpdate()->firstOrFail();

            if ($order->approved_at !== null || $order->status === PaymentOrderStatus::Approved) {
                throw new \DomainException('Cannot reject an approved payment order.');
            }
            if ($order->status !== PaymentOrderStatus::AwaitingReview) {
                throw new \DomainException('Payment order is not awaiting review.');
            }

            $before = $order->only(['status']);

            $order->status = PaymentOrderStatus::Rejected;
            $order->save();

            BankTransferSubmission::query()
                ->where('payment_order_id', $order->id)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['status' => BankTransferReviewStatus::Rejected->value]);

            $this->auditLogService->log(
                $adminUserId,
                'reject_transfer',
                'PaymentOrder',
                $order->id,
                $before,
                ['status' => $order->status->value, 'reason' => $reason],
                [],
            );

            return $order->fresh() ?? $order;
        });
    }
}
