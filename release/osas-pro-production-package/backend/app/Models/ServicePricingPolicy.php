<?php

namespace App\Models;

use App\Enums\ServicePricingPolicyType;
use Illuminate\Database\Eloquent\Model;

class ServicePricingPolicy extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'branch_id', 'policy_type', 'service_id', 'unit_price', 'tax_rate',
        'status', 'effective_from', 'effective_to', 'customer_id', 'customer_group_id',
        'contract_id', 'priority', 'notes',
    ];

    protected $casts = [
        'policy_type' => ServicePricingPolicyType::class,
        'unit_price' => 'decimal:4',
        'tax_rate' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'priority' => 'integer',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
