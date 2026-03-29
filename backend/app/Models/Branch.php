<?php

namespace App\Models;

use App\Enums\BranchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'name', 'name_ar', 'code',
        'phone', 'address', 'city', 'is_main', 'is_active',
        'status', 'cross_branch_access',
    ];

    protected $casts = [
        'is_main'             => 'boolean',
        'is_active'           => 'boolean',
        'cross_branch_access' => 'boolean',
        'status'              => BranchStatus::class,
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function inventory()
    {
        return $this->hasMany(Inventory::class);
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->status === BranchStatus::Active;
    }
}
