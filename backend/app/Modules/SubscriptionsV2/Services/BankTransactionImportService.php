<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Jobs\SubscriptionsV2\RunReconciliationJob;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Support\Str;

final class BankTransactionImportService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {}

    /**
     * @param  list<array{
     *   amount: float|int|string,
     *   transaction_date: string,
     *   transaction_time?: ?string,
     *   currency?: string,
     *   sender_name?: ?string,
     *   bank_reference?: ?string,
     *   description?: ?string,
     * }>  $rows
     * @return list<int> inserted ids
     */
    public function import(array $rows, ?int $actorId = null, ?string $batchUuid = null): array
    {
        $batchUuid ??= (string) Str::uuid();
        $ids       = [];

        foreach ($rows as $row) {
            $refRaw = $row['bank_reference'] ?? $row['description'] ?? '';
            $extracted = $this->extractReference((string) $refRaw);

            $tx = BankTransaction::query()->create([
                'import_batch_uuid'   => $batchUuid,
                'transaction_date'    => $row['transaction_date'],
                'transaction_time'    => $row['transaction_time'] ?? null,
                'amount'              => $row['amount'],
                'currency'            => $row['currency'] ?? 'SAR',
                'sender_name'         => $row['sender_name'] ?? null,
                'bank_reference'      => $row['bank_reference'] ?? null,
                'description'         => $row['description'] ?? null,
                'reference_extracted' => $extracted,
                'is_matched'          => false,
            ]);
            $ids[] = $tx->id;
        }

        if ($ids !== []) {
            $this->auditLogService->log(
                $actorId,
                'import_bank_transactions',
                'BankTransaction',
                $ids[0],
                null,
                ['batch_uuid' => $batchUuid, 'count' => count($ids)],
                ['ids' => $ids],
            );
        }

        PaymentOrder::query()
            ->where('status', PaymentOrderStatus::AwaitingReview)
            ->orderBy('id')
            ->limit(200)
            ->pluck('id')
            ->each(static function (int $paymentOrderId): void {
                RunReconciliationJob::dispatch($paymentOrderId)->onQueue('high');
            });

        return $ids;
    }

    public function extractReference(string $haystack): ?string
    {
        if ($haystack === '') {
            return null;
        }

        if (preg_match('/\b(SUB-ORD-[A-Z0-9]{10,})\b/i', $haystack, $m)) {
            return strtoupper($m[1]);
        }

        if (preg_match('/\b([A-Z]{2,4}-?\d{6,})\b/', $haystack, $m)) {
            return strtoupper(str_replace('-', '', $m[1]));
        }

        if (preg_match('/\b(\d{12,20})\b/', $haystack, $m)) {
            return $m[1];
        }

        return null;
    }
}
