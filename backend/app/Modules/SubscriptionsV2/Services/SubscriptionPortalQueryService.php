<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\Cache;

final class SubscriptionPortalQueryService
{
    public function __construct(
        private readonly SubscriptionCacheService $cache,
    ) {}

    /**
     * @return array{subscription: ?Subscription, plan: ?Plan}
     */
    public function current(int $companyId): array
    {
        /** @var array{subscription: ?Subscription, plan: ?Plan} $data */
        $data = Cache::remember($this->cache->currentKey($companyId), 60, function () use ($companyId): array {
            $subscription = Subscription::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->latest('id')
                ->first();
            $plan = $subscription !== null ? Plan::query()->where('slug', $subscription->plan)->first() : null;

            return ['subscription' => $subscription, 'plan' => $plan];
        });

        return $data;
    }

    public function plans(): mixed
    {
        return Cache::remember($this->cache->plansKey(), 600, static fn () => Plan::query()->where('is_active', true)->orderBy('sort_order')->get());
    }

    /**
     * @return array{wallet: ?Wallet, transactions: mixed}
     */
    public function wallet(int $companyId): array
    {
        /** @var array{wallet: ?Wallet, transactions: mixed} $data */
        $data = Cache::remember($this->cache->walletKey($companyId), 30, function () use ($companyId): array {
            $wallet = Wallet::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->whereNull('customer_id')
                ->first();
            $transactions = collect();
            if ($wallet !== null) {
                $transactions = WalletTransaction::withoutGlobalScopes()
                    ->where('wallet_id', $wallet->id)
                    ->orderByDesc('id')
                    ->limit(20)
                    ->get(['id', 'type', 'amount', 'reference_type', 'reference_id', 'balance_after', 'created_by_user_id', 'created_at']);
            }

            return ['wallet' => $wallet, 'transactions' => $transactions];
        });

        return $data;
    }

    public function invoices(int $companyId): mixed
    {
        return Invoice::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('type', 'subscription')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'invoice_number', 'status', 'total', 'paid_amount', 'due_amount', 'currency', 'issued_at', 'due_at']);
    }
}

