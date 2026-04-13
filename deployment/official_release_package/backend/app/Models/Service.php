<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'created_by_user_id',
        'name', 'name_ar', 'code', 'description',
        'base_price', 'tax_rate', 'estimated_minutes', 'is_active',
    ];

    protected $casts = [
        'base_price'        => 'decimal:4',
        'tax_rate'          => 'decimal:2',
        'estimated_minutes' => 'integer',
        'is_active'         => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function bundleItems()
    {
        return $this->hasMany(BundleItem::class);
    }
}
