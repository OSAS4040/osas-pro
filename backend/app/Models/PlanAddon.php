<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanAddon extends Model
{
    protected $fillable = [
        'slug',
        'feature_key',
        'name',
        'name_ar',
        'description_ar',
        'price_monthly',
        'price_yearly',
        'currency',
        'eligible_plan_slugs',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly'       => 'decimal:2',
        'price_yearly'        => 'decimal:2',
        'eligible_plan_slugs' => 'array',
        'is_active'           => 'boolean',
    ];

    public function subscriptionAddons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class, 'plan_addon_id');
    }

    public function isEligibleForPlanSlug(string $planSlug): bool
    {
        $el = $this->eligible_plan_slugs;
        if (! is_array($el) || $el === []) {
            return true;
        }

        return in_array($planSlug, $el, true);
    }
}
