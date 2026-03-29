<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleSetting extends Model
{
    protected $fillable = [
        'vehicle_id', 'oil_type', 'oil_capacity_liters', 'oil_change_interval_km',
        'last_oil_change_km', 'last_oil_change_date', 'tire_size', 'tire_brand',
        'tire_change_date', 'battery_brand', 'battery_capacity_ah', 'battery_change_date',
        'ac_gas_type', 'last_ac_service_date', 'insurance_expiry', 'registration_expiry',
        'next_inspection_date', 'custom_settings',
    ];

    protected $casts = [
        'last_oil_change_date'  => 'date',
        'tire_change_date'      => 'date',
        'battery_change_date'   => 'date',
        'last_ac_service_date'  => 'date',
        'insurance_expiry'      => 'date',
        'registration_expiry'   => 'date',
        'next_inspection_date'  => 'date',
        'custom_settings'       => 'array',
    ];

    public function vehicle(): BelongsTo { return $this->belongsTo(Vehicle::class); }
}
