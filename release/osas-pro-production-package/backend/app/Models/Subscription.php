<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'plan', 'status', 'starts_at', 'ends_at',
        'grace_ends_at', 'amount', 'currency', 'features',
        'max_branches', 'max_users',
    ];

    protected $casts = [
        'status'        => SubscriptionStatus::class,
        'starts_at'     => 'datetime',
        'ends_at'       => 'datetime',
        'grace_ends_at' => 'datetime',
        'features'      => 'array',
        'amount'        => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isActive(): bool
    {
        return $this->status === SubscriptionStatus::Active;
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === SubscriptionStatus::GracePeriod;
    }
}
