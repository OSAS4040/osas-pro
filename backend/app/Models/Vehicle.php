<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'customer_id', 'created_by_user_id',
        'plate_number', 'vin', 'make', 'model', 'year', 'color',
        'engine_type', 'fuel_type', 'transmission',
        'mileage_in', 'notes', 'is_active', 'version',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'year'      => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function workOrders()
    {
        return $this->hasMany(WorkOrder::class);
    }
}
