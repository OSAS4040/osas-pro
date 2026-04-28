<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Jobs\SubscriptionsV2\RunReconciliationJob;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use App\Modules\SubscriptionsV2\Services\BankTransferService;
use App\Modules\SubscriptionsV2\Services\RealtimeNotificationService;
use Illuminate\Support\Facades\DB;

final class SubmitBankTransferAction
{
    public function __construct(
        private readonly BankTransferService $bankTransferService,
        private readonly AuditLogService $auditLogService,
        private readonly RealtimeNotificationService $realtimeNotificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(PaymentOrder $order, array $data, int $submittedByUserId): BankTransferSubmission
    {
        return DB::transaction(function () use ($order, $data, $submittedByUserId): BankTransferSubmission {
            $locked = PaymentOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $submission = $this->bankTransferService->submitTransfer($locked, $data, $submittedByUserId);

            $this->auditLogService->log(
                $submittedByUserId,
                'submit_transfer',
                'BankTransferSubmission',
                $submission->id,
                null,
                ['payment_order_id' => $locked->id, 'amount' => (string) $data['amount']],
                ['payment_order_status' => $locked->fresh()?->status->value],
            );

            $orderId = (int) $locked->id;
            $companyId = (int) $locked->company_id;
            DB::afterCommit(function () use ($orderId, $companyId): void {
                RunReconciliationJob::dispatch($orderId)->onQueue('high');
                $this->realtimeNotificationService->publish(
                    'transfer_submitted',
                    $companyId,
                    'company',
                    [
                        'type' => 'transfer_submitted',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تم استلام التحويل وجارٍ التحقق.',
                    ],
                );
                $this->realtimeNotificationService->publish(
                    'transfer_submitted_admin',
                    null,
                    'admin',
                    [
                        'type' => 'transfer_submitted',
                        'company_id' => $companyId,
                        'payment_order_id' => $orderId,
                        'message' => 'تحويل جديد بحاجة مراجعة.',
                    ],
                );
            });

            return $submission;
        });
    }
}
