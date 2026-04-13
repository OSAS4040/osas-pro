<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Wallet extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id',
        'balance', 'currency', 'status', 'credit_limit', 'notes', 'version',
    ];

    protected $casts = [
        'balance'      => 'decimal:4',
        'credit_limit' => 'decimal:4',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function transactions()
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function availableBalance(): float
    {
        return (float) $this->balance + (float) $this->credit_limit;
    }
}
