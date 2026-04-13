<?php

namespace App\Models;

use App\Enums\WalletType;
use App\Models\HasTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerWallet extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'vehicle_id',
        'wallet_type', 'status', 'balance', 'currency', 'version',
    ];

    protected $casts = [
        'wallet_type' => WalletType::class,
        'balance'     => 'decimal:4',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'customer_wallet_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function availableBalance(): float
    {
        return (float) $this->balance;
    }

    public function isVehicleWallet(): bool
    {
        return $this->wallet_type === WalletType::VehicleWallet;
    }

    public function isFleetMain(): bool
    {
        return $this->wallet_type === WalletType::FleetMain;
    }

    public function isCustomerMain(): bool
    {
        return $this->wallet_type === WalletType::CustomerMain;
    }
}
