<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use App\Modules\SubscriptionsV2\Services\InvoiceService;
use App\Modules\SubscriptionsV2\Services\PaymentService;
use App\Modules\SubscriptionsV2\Services\SubscriptionService;
use App\Modules\SubscriptionsV2\Services\SubscriptionCacheService;
use App\Modules\SubscriptionsV2\Services\SubscriptionWalletService;
use App\Modules\SubscriptionsV2\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\DB;

final class ApproveBankTransferAction
{
    public function __construct(
        private readonly PaymentService $paymentService,
        private readonly InvoiceService $invoiceService,
        private readonly SubscriptionService $subscriptionService,
        private readonly SubscriptionWalletService $walletService,
        private readonly SubscriptionCacheService $subscriptionCacheService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @return array{order: PaymentOrder, payment_id: int, invoice_id: int}
     */
    public function execute(int $paymentOrderId, int $adminUserId): array
    {
        return DB::transaction(function () use ($paymentOrderId, $adminUserId): array {
            /** @var PaymentOrder $order */
            $order = PaymentOrder::query()->whereKey($paymentOrderId)->lockForUpdate()->firstOrFail();

            if ($order->approved_at !== null || $order->status === PaymentOrderStatus::Approved) {
                throw new \DomainException('Payment order already approved.');
            }
            if (! in_array($order->status, [PaymentOrderStatus::AwaitingReview, PaymentOrderStatus::Matched], true)) {
                throw new \DomainException('Payment order is not ready for financial approval.');
            }
            if (! $order->hasConfirmedMatch()) {
                throw new \DomainException('No reconciliation match.');
            }

            $beforeOrder = $order->only(['status', 'approved_at', 'approved_by']);
            $walletBalance = $this->walletService->getBalance((int) $order->company_id);
            $total = (float) $order->total;
            $walletAmount = min($walletBalance, $total);
            $bankAmount = max(0.0, round($total - $walletAmount, 2));
            if ($walletAmount > 0) {
                $this->walletService->debit(
                    (int) $order->company_id,
                    $walletAmount,
                    'payment_order_approval_'.$order->id,
                    $adminUserId,
                    'po-approve-'.$order->id,
                );
            }

            if ($walletAmount >= $total) {
                $payment = $this->paymentService->createFromWallet($order, $adminUserId, $walletAmount);
            } elseif ($walletAmount > 0) {
                $payment = $this->paymentService->createHybridPayment($order, $adminUserId, $walletAmount, $bankAmount);
            } else {
                $payment = $this->paymentService->createFromPaymentOrder($order, $adminUserId);
            }

            $this->auditLogService->log(
                $adminUserId,
                'create_payment',
                'Payment',
                $payment->id,
                null,
                [
                    'amount'   => (string) $payment->amount,
                    'currency' => (string) $payment->currency,
                    'status'   => (string) $payment->status,
                ],
                ['payment_order_id' => $order->id],
            );

            $invoice = $this->invoiceService->createFromPayment($payment, $order, $adminUserId);

            $paymentFresh = $payment->fresh();
            if ($paymentFresh === null) {
                throw new \DomainException('Payment disappeared after invoice creation.');
            }

            $this->subscriptionService->activateFromPayment($order, $paymentFresh);

            $order->status      = PaymentOrderStatus::Approved;
            $order->approved_at = now();
            $order->approved_by = $adminUserId;
            $order->save();
            $companyId = (int) $order->company_id;
            $orderId = (int) $order->id;
            DB::afterCommit(function () use ($companyId, $orderId): void {
                $this->subscriptionCacheService->invalidateCompany($companyId);
                $this->subscriptionCacheService->invalidateGlobal();
                $this->realtimeNotificationService->publish(
                    'transfer_approved',
                    $companyId,
                    'company',
                    [
                        'type' => 'transfer_approved',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تم تفعيل اشتراكك بنجاح.',
                    ],
                );
                $this->realtimeNotificationService->publish(
                    'subscription_activated',
                    $companyId,
                    'company',
                    [
                        'type' => 'subscription_activated',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'اشتراكك الآن نشط.',
                    ],
                );
                $this->realtimeNotificationService->publish(
                    'transfer_approved_admin',
                    null,
                    'admin',
                    [
                        'type' => 'transfer_approved',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تم اعتماد طلب الدفع.',
                    ],
                );
            });

            $this->auditLogService->log(
                $adminUserId,
                'approve_transfer',
                'PaymentOrder',
                $order->id,
                $beforeOrder,
                $order->only(['status', 'approved_at', 'approved_by']),
                ['payment_id' => $payment->id, 'invoice_id' => $invoice->id],
            );

            return [
                'order'      => $order->fresh() ?? $order,
                'payment_id' => $payment->id,
                'invoice_id' => $invoice->id,
            ];
        });
    }
}
