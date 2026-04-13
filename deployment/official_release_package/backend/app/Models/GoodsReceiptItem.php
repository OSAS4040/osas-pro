<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodsReceiptItem extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'goods_receipt_id', 'purchase_item_id',
        'product_id', 'expected_quantity', 'received_quantity',
        'unit_cost', 'stock_movement_id', 'notes',
    ];

    protected $casts = [
        'expected_quantity'  => 'decimal:4',
        'received_quantity'  => 'decimal:4',
        'unit_cost'          => 'decimal:4',
    ];

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseItem()
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }
}
