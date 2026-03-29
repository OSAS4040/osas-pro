<?php

namespace App\Models;

use App\Enums\WorkOrderItemType;
use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    protected $fillable = [
        'company_id', 'work_order_id', 'product_id', 'item_type', 'name', 'sku',
        'quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total',
    ];

    protected $casts = [
        'item_type'       => WorkOrderItemType::class,
        'quantity'        => 'decimal:4',
        'unit_price'      => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate'        => 'decimal:2',
        'tax_amount'      => 'decimal:4',
        'subtotal'        => 'decimal:4',
        'total'           => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
