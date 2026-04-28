<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Enums\ReconciliationMatchStatus;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Models\ReconciliationMatch;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use App\Modules\SubscriptionsV2\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\DB;

final class RejectReviewAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    public function execute(int $paymentOrderId, int $adminUserId, string $reason): PaymentOrder
    {
        return DB::transaction(function () use ($paymentOrderId, $adminUserId, $reason): PaymentOrder {
            $order = PaymentOrder::query()->whereKey($paymentOrderId)->lockForUpdate()->firstOrFail();

            if ($order->status === PaymentOrderStatus::Approved) {
                throw new \DomainException('Cannot reject review on an approved payment order.');
            }

            $confirmed = ReconciliationMatch::query()
                ->where('payment_order_id', $order->id)
                ->where('status', ReconciliationMatchStatus::Confirmed)
                ->get();

            foreach ($confirmed as $m) {
                BankTransaction::query()->whereKey($m->bank_transaction_id)->update(['is_matched' => false]);
            }

            ReconciliationMatch::query()
                ->where('payment_order_id', $order->id)
                ->update([
                    'status'         => ReconciliationMatchStatus::Rejected,
                    'decision_notes' => $reason,
                ]);

            $order->status = PaymentOrderStatus::Rejected;
            $order->save();
            $companyId = (int) $order->company_id;
            $orderId = (int) $order->id;
            DB::afterCommit(function () use ($companyId, $orderId, $reason): void {
                $this->realtimeNotificationService->publish(
                    'transfer_rejected',
                    $companyId,
                    'company',
                    [
                        'type' => 'transfer_rejected',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تم رفض طلب الدفع.',
                        'reason' => $reason,
                    ],
                );
                $this->realtimeNotificationService->publish(
                    'transfer_rejected_admin',
                    null,
                    'admin',
                    [
                        'type' => 'transfer_rejected',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تم رفض طلب الدفع بعد المراجعة.',
                    ],
                );
            });

            $this->auditLogService->log(
                $adminUserId,
                'reject_review',
                'PaymentOrder',
                $order->id,
                null,
                ['status' => $order->status->value, 'reason' => $reason],
                [],
            );

            return $order->fresh() ?? $order;
        });
    }
}
