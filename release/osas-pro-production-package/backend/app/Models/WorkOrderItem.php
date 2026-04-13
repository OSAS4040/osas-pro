<?php

namespace App\Models;

use App\Enums\WorkOrderItemType;
use Illuminate\Database\Eloquent\Model;

class WorkOrderItem extends Model
{
    protected $fillable = [
        'company_id', 'work_order_id', 'product_id', 'service_id', 'item_type', 'name', 'sku',
        'quantity', 'unit_price', 'discount_amount', 'tax_rate', 'tax_amount', 'subtotal', 'total',
        'pricing_source', 'pricing_policy_id', 'pricing_contract_service_item_id',
        'pricing_resolved_at', 'pricing_resolved_by_system', 'pricing_notes',
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
        'pricing_resolved_at' => 'datetime',
        'pricing_resolved_by_system' => 'boolean',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function pricingPolicy()
    {
        return $this->belongsTo(ServicePricingPolicy::class, 'pricing_policy_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function contractServiceItem()
    {
        return $this->belongsTo(ContractServiceItem::class, 'pricing_contract_service_item_id');
    }
}
