<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'name_ar',
        'price_monthly',
        'price_yearly',
        'currency',
        'max_branches',
        'max_users',
        'max_products',
        'grace_period_days',
        'features',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price_monthly'    => 'decimal:2',
        'price_yearly'     => 'decimal:2',
        'features'         => 'array',
        'is_active'        => 'boolean',
    ];

    public static function findBySlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'plan', 'slug');
    }

    public function hasFeature(string $key): bool
    {
        return in_array($key, $this->features ?? []);
    }

    public function getLimit(string $key, mixed $default = null): mixed
    {
        $limits = [
            'max_branches' => $this->max_branches,
            'max_users'    => $this->max_users,
            'max_products' => $this->max_products,
        ];
        return $limits[$key] ?? $default;
    }
}
