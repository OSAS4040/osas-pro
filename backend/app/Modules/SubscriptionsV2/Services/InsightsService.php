<?php

declare(strict_types=1);

namespace App\Modules\SubscriptionsV2\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Wallet;

final class InsightsService
{
    /**
     * @return array<string, mixed>
     */
    public function getRevenueSummary(): array
    {
        $today = now()->toDateString();
        $monthStart = now()->startOfMonth();

        return [
            'daily_revenue' => (string) Payment::withoutGlobalScopes()->whereDate('created_at', $today)->sum('amount'),
            'monthly_revenue' => (string) Payment::withoutGlobalScopes()->where('created_at', '>=', $monthStart)->sum('amount'),
            'active_subscriptions' => Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::Active)->count(),
            'past_due_count' => Subscription::withoutGlobalScopes()->where('status', SubscriptionStatus::PastDue)->count(),
        ];
    }

    public function getChurnSignals(): array
    {
        $rows = Subscription::withoutGlobalScopes()
            ->whereIn('status', [SubscriptionStatus::PastDue, SubscriptionStatus::Suspended, SubscriptionStatus::Expired, SubscriptionStatus::Active])
            ->get(['id', 'company_id', 'status', 'ends_at', 'grace_ends_at', 'plan']);
        $planSlugs = $rows->pluck('plan')->filter()->unique()->values();
        $plansBySlug = Plan::query()->whereIn('slug', $planSlugs)->get(['slug', 'price_monthly'])->keyBy('slug');
        $companyIds = $rows->pluck('company_id')->filter()->unique()->values();
        $walletsByCompany = Wallet::withoutGlobalScopes()
            ->whereNull('customer_id')
            ->whereIn('company_id', $companyIds)
            ->get(['company_id', 'balance'])
            ->keyBy('company_id');

        $out = [];
        foreach ($rows as $sub) {
            $plan = $plansBySlug->get((string) $sub->plan);
            $monthly = (float) ($plan?->price_monthly ?? 0);
            $wallet = (float) ($walletsByCompany->get((int) $sub->company_id)?->balance ?? 0);
            $pastDueDays = $sub->grace_ends_at !== null ? max(0, now()->diffInDays($sub->grace_ends_at, false) * -1) : 0;

            $risk = 'low';
            if ($sub->status === SubscriptionStatus::PastDue && $pastDueDays > 2) {
                $risk = 'high';
            } elseif ($wallet > 0 && $monthly > 0 && $wallet < ($monthly / 10)) {
                $risk = 'medium';
            }

            $out[] = [
                'subscription_id' => $sub->id,
                'company_id' => $sub->company_id,
                'status' => $sub->status->value,
                'risk_level' => $risk,
                'signals' => [
                    'past_due_days' => $pastDueDays,
                    'wallet_balance' => $wallet,
                ],
            ];
        }

        return $out;
    }

    public function getRiskySubscriptions(): array
    {
        return array_values(array_filter($this->getChurnSignals(), static fn (array $r): bool => in_array($r['risk_level'], ['high', 'medium'], true)));
    }

    /**
     * إشارة مخاطرة واحدة لاشتراك محدد (قراءة فقط) — يُفضّل على جلب كل الإشارات ثم التصفية.
     *
     * @return array<string, mixed>|null
     */
    public function getChurnSignalForSubscriptionId(int $subscriptionId): ?array
    {
        $sub = Subscription::withoutGlobalScopes()->find($subscriptionId);
        if ($sub === null) {
            return null;
        }

        $plan = Plan::query()->where('slug', (string) $sub->plan)->first();
        $monthly = (float) ($plan?->price_monthly ?? 0);
        $wallet = (float) (Wallet::withoutGlobalScopes()
            ->whereNull('customer_id')
            ->where('company_id', (int) $sub->company_id)
            ->value('balance') ?? 0);
        $pastDueDays = $sub->grace_ends_at !== null ? max(0, now()->diffInDays($sub->grace_ends_at, false) * -1) : 0;

        $risk = 'low';
        if ($sub->status === SubscriptionStatus::PastDue && $pastDueDays > 2) {
            $risk = 'high';
        } elseif ($wallet > 0 && $monthly > 0 && $wallet < ($monthly / 10)) {
            $risk = 'medium';
        }

        return [
            'subscription_id' => (int) $sub->id,
            'company_id' => (int) $sub->company_id,
            'status' => $sub->status instanceof SubscriptionStatus ? $sub->status->value : (string) $sub->status,
            'risk_level' => $risk,
            'signals' => [
                'past_due_days' => $pastDueDays,
                'wallet_balance' => $wallet,
            ],
        ];
    }

    public function getWalletCoverageInsights(): array
    {
        $subs = Subscription::withoutGlobalScopes()->get(['id', 'company_id', 'plan']);
        $planSlugs = $subs->pluck('plan')->filter()->unique()->values();
        $plansBySlug = Plan::query()->whereIn('slug', $planSlugs)->get(['slug', 'price_monthly'])->keyBy('slug');
        $companyIds = $subs->pluck('company_id')->filter()->unique()->values();
        $walletsByCompany = Wallet::withoutGlobalScopes()
            ->whereNull('customer_id')
            ->whereIn('company_id', $companyIds)
            ->get(['company_id', 'balance'])
            ->keyBy('company_id');
        $out = [];
        foreach ($subs as $sub) {
            $plan = $plansBySlug->get((string) $sub->plan);
            $monthly = (float) ($plan?->price_monthly ?? 0);
            $wallet = (float) ($walletsByCompany->get((int) $sub->company_id)?->balance ?? 0);
            $days = $monthly > 0 ? (int) floor($wallet / ($monthly / 30)) : 0;

            $out[] = [
                'subscription_id' => $sub->id,
                'company_id' => $sub->company_id,
                'wallet_balance' => $wallet,
                'estimated_days_coverage' => $days,
                'message' => $days < 4 ? 'رصيد منخفض — قد لا يكفي للتجديد القادم.' : 'الرصيد مناسب للتجديد القادم.',
            ];
        }

        return $out;
    }
}

