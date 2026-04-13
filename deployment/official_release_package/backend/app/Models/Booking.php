<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Booking extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid','company_id','branch_id','bay_id','customer_id','vehicle_id',
        'work_order_id','starts_at','ends_at','duration_minutes','status',
        'service_type','deposit_amount','source','notes',
        'created_by','cancelled_by','cancellation_reason',
    ];

    protected $casts = [
        'starts_at'      => 'datetime',
        'ends_at'        => 'datetime',
        'deposit_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function bay(): BelongsTo      { return $this->belongsTo(Bay::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function vehicle(): BelongsTo  { return $this->belongsTo(Vehicle::class); }
}
