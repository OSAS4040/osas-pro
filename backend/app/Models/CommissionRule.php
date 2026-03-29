<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRule extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'employee_id', 'applies_to', 'rate', 'min_amount', 'is_active',
    ];

    protected $casts = [
        'rate'       => 'decimal:2',
        'min_amount' => 'decimal:2',
        'is_active'  => 'boolean',
    ];
}
