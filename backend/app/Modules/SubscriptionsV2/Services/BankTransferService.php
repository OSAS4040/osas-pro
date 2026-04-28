<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Modules\SubscriptionsV2\Enums\BankTransferReviewStatus;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;

final class BankTransferService
{
    public function __construct(
        private readonly PaymentOrderService $paymentOrderService,
    ) {}

    /**
     * @param  array{
     *   amount: float|int|string,
     *   transfer_date: string,
     *   transfer_time?: ?string,
     *   bank_name: string,
     *   sender_name?: ?string,
     *   sender_account_masked?: ?string,
     *   bank_reference?: ?string,
     *   receipt_path?: ?string,
     *   receipt_original_name?: ?string,
     *   notes?: ?string,
     * }  $data
     */
    public function submitTransfer(PaymentOrder $order, array $data, int $submittedByUserId): BankTransferSubmission
    {
        if ($order->status !== PaymentOrderStatus::PendingTransfer) {
            throw new \DomainException('Transfer can only be submitted while order is pending_transfer.');
        }
        if (now()->greaterThan($order->expires_at)) {
            throw new \DomainException('Payment order has expired.');
        }

        $submission = BankTransferSubmission::query()->create([
            'payment_order_id'        => $order->id,
            'submitted_by'            => $submittedByUserId,
            'amount'                  => $data['amount'],
            'transfer_date'           => $data['transfer_date'],
            'transfer_time'           => $data['transfer_time'] ?? null,
            'bank_name'               => $data['bank_name'],
            'sender_name'             => $data['sender_name'] ?? null,
            'sender_account_masked'   => $data['sender_account_masked'] ?? null,
            'bank_reference'          => $data['bank_reference'] ?? null,
            'receipt_path'            => $data['receipt_path'] ?? null,
            'receipt_original_name'   => $data['receipt_original_name'] ?? null,
            'status'                  => BankTransferReviewStatus::UnderReview,
            'notes'                   => $data['notes'] ?? null,
        ]);

        $this->paymentOrderService->markAwaitingReview($order->fresh() ?? $order);

        return $submission;
    }
}
