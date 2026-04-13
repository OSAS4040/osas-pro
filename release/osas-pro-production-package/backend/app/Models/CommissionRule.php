<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionRule extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id',
        'name',
        'employee_id',
        'customer_id',
        'applies_to',
        'rate',
        'min_amount',
        'max_commission_amount',
        'attendance_multiplier',
        'priority',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'rate'                    => 'decimal:2',
        'min_amount'              => 'decimal:2',
        'max_commission_amount'   => 'decimal:2',
        'attendance_multiplier'   => 'decimal:2',
        'is_active'               => 'boolean',
        'priority'                => 'integer',
        'meta'                    => 'array',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
