<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'type', 'name', 'name_ar',
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
}
