<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $fillable = [
        'company_id', 'invoice_id',
        'product_id', 'service_id',
        'name', 'description', 'sku',
        'quantity', 'unit_price', 'cost_price',
        'discount_amount', 'tax_rate',
        'tax_amount', 'subtotal', 'total', 'line_total',
    ];

    protected $casts = [
        'quantity'        => 'decimal:4',
        'unit_price'      => 'decimal:4',
        'cost_price'      => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'tax_rate'        => 'decimal:2',
        'tax_amount'      => 'decimal:4',
        'subtotal'        => 'decimal:4',
        'total'           => 'decimal:4',
        'line_total'      => 'decimal:4',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
