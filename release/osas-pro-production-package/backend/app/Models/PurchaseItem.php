<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'company_id', 'purchase_id', 'product_id', 'name', 'sku',
        'quantity', 'received_quantity', 'unit_cost', 'tax_rate', 'tax_amount', 'total',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'unit_cost'         => 'decimal:4',
        'tax_rate'          => 'decimal:2',
        'tax_amount'        => 'decimal:4',
        'total'             => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
