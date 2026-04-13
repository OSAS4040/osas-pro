<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bundle extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'created_by_user_id',
        'name', 'name_ar', 'code', 'description',
        'base_price', 'override_item_prices', 'is_active',
    ];

    protected $casts = [
        'base_price'            => 'decimal:4',
        'override_item_prices'  => 'boolean',
        'is_active'             => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function items()
    {
        return $this->hasMany(BundleItem::class)->orderBy('sort_order');
    }

    public function calculateTotal(): float
    {
        if ($this->override_item_prices) {
            return (float) $this->base_price;
        }

        return $this->items->sum(function (BundleItem $item) {
            $price = $item->unit_price_override
                ?? ($item->service?->base_price ?? $item->product?->sale_price ?? 0);

            return $item->quantity * $price;
        });
    }
}
