<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\LedgerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class SubscriptionWalletService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
        private readonly LedgerService $ledgerService,
        private readonly SubscriptionCacheService $subscriptionCacheService,
    ) {}

    public function credit(int $companyId, float $amount, string $reason, int $actorId, ?string $idempotencyKey = null): WalletTransaction
    {
        return $this->record($companyId, abs($amount), true, $reason, $actorId, $idempotencyKey);
    }

    public function debit(int $companyId, float $amount, string $reason, int $actorId, ?string $idempotencyKey = null): WalletTransaction
    {
        return $this->record($companyId, abs($amount), false, $reason, $actorId, $idempotencyKey);
    }

    public function getBalance(int $companyId): float
    {
        $wallet = Wallet::query()->where('company_id', $companyId)->whereNull('customer_id')->first();

        return (float) ($wallet?->balance ?? 0.0);
    }

    private function record(
        int $companyId,
        float $amount,
        bool $isCredit,
        string $reason,
        int $actorId,
        ?string $idempotencyKey,
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new \DomainException('Wallet amount must be greater than zero.');
        }

        return DB::transaction(function () use ($companyId, $amount, $isCredit, $reason, $actorId, $idempotencyKey): WalletTransaction {
            /** @var Wallet $wallet */
            $wallet = Wallet::query()
                ->where('company_id', $companyId)
                ->whereNull('customer_id')
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = Wallet::query()->create([
                    'uuid'       => (string) Str::uuid(),
                    'company_id' => $companyId,
                    'customer_id' => null,
                    'balance'    => 0,
                    'currency'   => 'SAR',
                    'status'     => 'active',
                ]);
                $wallet = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            $before = (float) $wallet->balance;
            $after  = $isCredit ? ($before + $amount) : ($before - $amount);
            if ($after < -0.0001) {
                throw new \DomainException('Insufficient wallet balance.');
            }

            if ($idempotencyKey !== null) {
                $exists = WalletTransaction::query()
                    ->where('company_id', $companyId)
                    ->where('idempotency_key', $idempotencyKey)
                    ->exists();
                if ($exists) {
                    throw new \DomainException('Duplicate wallet debit/credit prevented.');
                }
            }

            $wallet->balance = $after;
            $wallet->version = (int) $wallet->version + 1;
            $wallet->save();

            $txn = WalletTransaction::query()->create([
                'uuid'               => (string) Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $wallet->branch_id,
                'created_by_user_id' => $actorId,
                'wallet_id'          => $wallet->id,
                'customer_wallet_id' => null,
                'type'               => $isCredit ? 'ADJUSTMENT_ADD' : 'INVOICE_DEBIT',
                'amount'             => $amount,
                'balance_before'     => $before,
                'balance_after'      => $after,
                'reference_type'     => $isCredit ? 'adjustment' : 'payment',
                'reference_id'       => null,
                'idempotency_key'    => $idempotencyKey,
                'trace_id'           => app()->bound('trace_id') ? (string) app('trace_id') : null,
                'note'               => $reason,
                'created_at'         => now(),
            ]);

            $this->postLedger($companyId, (float) $amount, $isCredit, $txn->id, $reason, $wallet->branch_id, $actorId);

            $this->auditLogService->log(
                $actorId,
                $isCredit ? 'wallet_credit' : 'wallet_debit',
                'WalletTransaction',
                $txn->id,
                ['balance_before' => (string) $before],
                ['balance_after' => (string) $after, 'amount' => (string) $amount],
                ['reason' => $reason],
            );
            DB::afterCommit(function () use ($companyId): void {
                $this->subscriptionCacheService->invalidateCompany($companyId);
                $this->subscriptionCacheService->invalidateGlobal();
            });

            return $txn;
        });
    }

    private function postLedger(
        int $companyId,
        float $amount,
        bool $isCredit,
        int $sourceId,
        string $reason,
        ?int $branchId,
        int $actorId,
    ): void {
        $lines = $isCredit
            ? [
                ['account_code' => '1020', 'type' => 'debit', 'amount' => $amount, 'description' => 'Bank funding wallet'],
                ['account_code' => '2420', 'type' => 'credit', 'amount' => $amount, 'description' => 'Wallet liability'],
            ]
            : [
                ['account_code' => '2420', 'type' => 'debit', 'amount' => $amount, 'description' => 'Wallet utilized'],
                ['account_code' => '4100', 'type' => 'credit', 'amount' => $amount, 'description' => 'Subscription revenue'],
            ];

        $this->ledgerService->post($companyId, [
            'type'        => $isCredit ? 'wallet_top_up' : 'wallet_debit',
            'description' => 'Subscriptions wallet '.($isCredit ? 'credit' : 'debit').': '.$reason,
            'source_type' => WalletTransaction::class,
            'source_id'   => $sourceId,
            'trace_id'    => app()->bound('trace_id') ? (string) app('trace_id') : ('subv2-wallet-'.$sourceId),
            'lines'       => $lines,
            'currency'    => 'SAR',
        ], $branchId, $actorId);
    }
}

