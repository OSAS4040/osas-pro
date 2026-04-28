<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionAddon extends Model
{
    protected $fillable = [
        'subscription_id',
        'plan_addon_id',
        'activated_at',
    ];

    protected $casts = [
        'activated_at' => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function planAddon(): BelongsTo
    {
        return $this->belongsTo(PlanAddon::class, 'plan_addon_id');
    }
}
