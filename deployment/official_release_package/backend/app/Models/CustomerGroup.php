<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGroup extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'is_active', 'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_group_id');
    }
}
