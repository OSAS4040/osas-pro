<?php

namespace App\Modules\SubscriptionsV2\Models;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    protected $fillable = [
        'subscription_id',
        'from_plan_id',
        'to_plan_id',
        'change_type',
        'proration_amount',
        'effective_at',
        'created_by',
    ];

    protected $casts = [
        'proration_amount' => 'decimal:2',
        'effective_at'      => 'datetime',
    ];

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function fromPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'from_plan_id');
    }

    public function toPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'to_plan_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
