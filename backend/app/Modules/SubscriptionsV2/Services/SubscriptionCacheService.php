<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use Illuminate\Support\Facades\Cache;

final class SubscriptionCacheService
{
    public function currentKey(int $companyId): string
    {
        return 'subscriptions.current.'.$companyId;
    }

    public function plansKey(): string
    {
        return 'subscriptions.plans';
    }

    public function walletKey(int $companyId): string
    {
        return 'subscriptions.wallet.'.$companyId;
    }

    public function adminOverviewKey(): string
    {
        return 'admin.subscriptions.overview';
    }

    public function invalidateCompany(int $companyId): void
    {
        Cache::forget($this->currentKey($companyId));
        Cache::forget($this->walletKey($companyId));
    }

    public function invalidateGlobal(): void
    {
        Cache::forget($this->plansKey());
        Cache::forget($this->adminOverviewKey());
    }
}

