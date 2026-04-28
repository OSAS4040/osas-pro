<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Actions;

use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransferSubmission;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\AuditLogService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class UploadReceiptAction
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    public function execute(
        PaymentOrder $order,
        UploadedFile $receipt,
        ?string $bankReference,
        ?string $notes,
        int $userId,
    ): BankTransferSubmission {
        return DB::transaction(function () use ($order, $receipt, $bankReference, $notes, $userId): BankTransferSubmission {
            $locked = PaymentOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            if (in_array($locked->status, [
                PaymentOrderStatus::Approved,
                PaymentOrderStatus::Rejected,
                PaymentOrderStatus::Cancelled,
                PaymentOrderStatus::Expired,
            ], true)) {
                throw new \DomainException('Receipt upload is not allowed for this payment order state.');
            }

            $submission = $locked->bankTransferSubmissions()->orderByDesc('id')->first();
            if ($submission === null) {
                throw new \DomainException('Submit bank transfer details before uploading a receipt.');
            }

            $ext = strtolower($receipt->getClientOriginalExtension());
            if (! in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true)) {
                throw new \DomainException('Receipt must be pdf, jpg, or png.');
            }

            $path = $receipt->store('bank-receipts', 'public');

            $submission->receipt_path          = $path;
            $submission->receipt_original_name = $receipt->getClientOriginalName();
            if ($bankReference !== null && $bankReference !== '') {
                $submission->bank_reference = $bankReference;
            }
            if ($notes !== null && $notes !== '') {
                $submission->notes = trim((string) ($submission->notes ?? '').($submission->notes ? "\n" : '').$notes);
            }
            $submission->save();

            $this->auditLogService->log(
                $userId,
                'upload_receipt',
                'BankTransferSubmission',
                $submission->id,
                null,
                ['receipt_path' => $path, 'payment_order_id' => $locked->id],
                [],
            );

            return $submission->fresh() ?? $submission;
        });
    }
}
