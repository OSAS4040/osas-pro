<?php

namespace App\Services\Saas;

use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use Illuminate\Support\Facades\Schema;

final class SubscriptionAddonEntitlements
{
    /**
     * @param  array<string, bool>  $baseFeatures
     * @return array<string, bool>
     */
    public function mergePurchasedFeatureKeys(Subscription $subscription, array $baseFeatures): array
    {
        if (! Schema::hasTable('subscription_addons') || ! Schema::hasTable('plan_addons')) {
            return $baseFeatures;
        }

        $keys = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->id)
            ->whereHas('planAddon', fn ($q) => $q->where('is_active', true))
            ->with('planAddon')
            ->get()
            ->pluck('planAddon.feature_key')
            ->filter()
            ->unique()
            ->values();

        foreach ($keys as $key) {
            $baseFeatures[(string) $key] = true;
        }

        return $baseFeatures;
    }

    public function attach(Subscription $subscription, PlanAddon $addon): SubscriptionAddon
    {
        if (! $addon->is_active) {
            throw new \InvalidArgumentException('الإضافة غير مفعّلة في الكتالوج.');
        }
        if (! $addon->isEligibleForPlanSlug((string) $subscription->plan)) {
            throw new \InvalidArgumentException('الباقة الحالية لا تسمح بهذه الإضافة.');
        }

        return SubscriptionAddon::query()->firstOrCreate(
            [
                'subscription_id' => $subscription->id,
                'plan_addon_id'     => $addon->id,
            ],
            ['activated_at' => now()],
        );
    }

    public function detach(Subscription $subscription, PlanAddon $addon): int
    {
        return SubscriptionAddon::query()
            ->where('subscription_id', $subscription->id)
            ->where('plan_addon_id', $addon->id)
            ->delete();
    }

    /**
     * بعد تغيير الباقة: إزالة الإضافات التي لم تعد مؤهّلة.
     */
    public function pruneIneligibleForPlan(Subscription $subscription): int
    {
        if (! Schema::hasTable('subscription_addons') || ! Schema::hasTable('plan_addons')) {
            return 0;
        }

        $planSlug = (string) $subscription->plan;

        $ids = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->id)
            ->with('planAddon')
            ->get()
            ->filter(fn (SubscriptionAddon $row) => $row->planAddon && ! $row->planAddon->isEligibleForPlanSlug($planSlug))
            ->pluck('id');

        if ($ids->isEmpty()) {
            return 0;
        }

        return SubscriptionAddon::query()->whereIn('id', $ids)->delete();
    }

    /**
     * @return list<array{slug: string, feature_key: string, name_ar: string, price_monthly: float, price_yearly: float, currency: string, activated_at: string|null}>
     */
    public function activeAddonPayloadForSubscription(Subscription $subscription): array
    {
        if (! Schema::hasTable('subscription_addons') || ! Schema::hasTable('plan_addons')) {
            return [];
        }

        return SubscriptionAddon::query()
            ->where('subscription_id', $subscription->id)
            ->whereHas('planAddon', fn ($q) => $q->where('is_active', true))
            ->with('planAddon')
            ->orderBy('activated_at')
            ->get()
            ->map(function (SubscriptionAddon $row) {
                $a = $row->planAddon;

                return [
                    'slug'           => $a ? (string) $a->slug : '',
                    'feature_key'    => $a ? (string) $a->feature_key : '',
                    'name_ar'        => $a ? (string) $a->name_ar : '',
                    'price_monthly'  => $a ? (float) $a->price_monthly : 0.0,
                    'price_yearly'   => $a ? (float) $a->price_yearly : 0.0,
                    'currency'       => $a ? (string) $a->currency : 'SAR',
                    'activated_at'   => $row->activated_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();
    }
}
