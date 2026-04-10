<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'created_by_user_id',
        'name', 'name_ar', 'code', 'email', 'phone',
        'tax_number', 'cr_number', 'address', 'city', 'country',
        'payment_terms', 'credit_limit', 'is_active', 'status', 'version',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'is_active'    => 'boolean',
    ];

    public function purchases()
    {
        return $this->hasMany(Purchase::class);
    }

    public function contracts()
    {
        return $this->hasMany(SupplierContract::class);
    }
}
