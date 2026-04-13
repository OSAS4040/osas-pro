<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelLog extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'branch_id', 'vehicle_id', 'driver_user_id', 'created_by',
        'log_date', 'liters', 'price_per_liter', 'odometer_before', 'odometer_after',
        'fuel_efficiency', 'fuel_type', 'station_name', 'payment_method',
        'notes', 'receipt_number', 'idempotency_key',
    ];

    protected $casts = [
        'log_date' => 'date',
        'liters' => 'float',
        'price_per_liter' => 'float',
        'total_cost' => 'float',
        'odometer_before' => 'float',
        'odometer_after' => 'float',
        'fuel_efficiency' => 'float',
    ];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
    public function driver(): BelongsTo  { return $this->belongsTo(User::class, 'driver_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
