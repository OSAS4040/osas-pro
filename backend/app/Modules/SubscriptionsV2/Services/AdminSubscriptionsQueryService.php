<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Modules\SubscriptionsV2\Models\BankTransaction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Support\Facades\Cache;

final class AdminSubscriptionsQueryService
{
    public function __construct(
        private readonly SubscriptionCacheService $cache,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        /** @var array<string, mixed> $data */
        $data = Cache::remember($this->cache->adminOverviewKey(), 60, static function (): array {
            return [
                'subscription_status_counts' => Subscription::withoutGlobalScopes()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
                'payment_order_status_counts' => PaymentOrder::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
                'total_wallet_balance' => (string) Wallet::withoutGlobalScopes()->whereNull('customer_id')->sum('balance'),
            ];
        });

        return $data;
    }

    public function transactions(int $perPage = 50): mixed
    {
        return BankTransaction::query()
            ->with(['reconciliationMatches' => fn ($q) => $q->select('id', 'bank_transaction_id', 'payment_order_id', 'status', 'score', 'match_type')])
            ->orderByDesc('id')
            ->paginate($perPage, ['id', 'transaction_date', 'amount', 'currency', 'sender_name', 'bank_reference', 'description', 'reference_extracted', 'is_matched', 'created_at']);
    }

    public function wallets(int $walletPerPage = 50, int $transactionsLimit = 100): array
    {
        return [
            'wallets' => Wallet::withoutGlobalScopes()
                ->whereNull('customer_id')
                ->with('company:id,name')
                ->orderByDesc('balance')
                ->paginate($walletPerPage, ['id', 'company_id', 'balance', 'currency', 'status', 'updated_at']),
            'recent_transactions' => WalletTransaction::withoutGlobalScopes()
                ->orderByDesc('id')
                ->limit($transactionsLimit)
                ->get(['id', 'company_id', 'wallet_id', 'type', 'amount', 'balance_after', 'reference_type', 'created_at']),
        ];
    }
}

