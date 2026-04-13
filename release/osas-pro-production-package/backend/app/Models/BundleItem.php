<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BundleItem extends Model
{
    protected $fillable = [
        'bundle_id', 'item_type',
        'service_id', 'product_id',
        'quantity', 'unit_price_override',
        'notes', 'sort_order',
    ];

    protected $casts = [
        'quantity'            => 'decimal:4',
        'unit_price_override' => 'decimal:4',
        'sort_order'          => 'integer',
    ];

    public function bundle()
    {
        return $this->belongsTo(Bundle::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function resolvedPrice(): float
    {
        if ($this->unit_price_override !== null) {
            return (float) $this->unit_price_override;
        }

        return match ($this->item_type) {
            'service' => (float) ($this->service?->base_price ?? 0),
            'product' => (float) ($this->product?->sale_price ?? 0),
            default   => 0,
        };
    }

    public function lineTotal(): float
    {
        return (float) $this->quantity * $this->resolvedPrice();
    }
}
