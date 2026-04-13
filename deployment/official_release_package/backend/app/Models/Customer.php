<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_group_id', 'pricing_contract_id',
        'customer_pricing_profile', 'type', 'name', 'name_ar',
        'email', 'phone', 'tax_number', 'cr_number',
        'address', 'city', 'credit_limit', 'is_active',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class);
    }

    public function customerGroup()
    {
        return $this->belongsTo(CustomerGroup::class, 'customer_group_id');
    }

    public function pricingContract()
    {
        return $this->belongsTo(Contract::class, 'pricing_contract_id');
    }
}
