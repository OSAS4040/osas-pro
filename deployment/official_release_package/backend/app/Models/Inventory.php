<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    use HasTenantScope;

    protected $table = 'inventory';

    protected $fillable = [
        'company_id', 'branch_id', 'product_id',
        'quantity', 'reserved_quantity', 'reorder_point', 'version',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'reorder_point'     => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function getAvailableQuantityAttribute(): float
    {
        return (float) $this->quantity - (float) $this->reserved_quantity;
    }
}
