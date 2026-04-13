<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'employee_id', 'source_type', 'source_id',
        'base_amount', 'rate', 'amount', 'status', 'paid_at', 'paid_by', 'notes',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'rate'        => 'decimal:2',
        'amount'      => 'decimal:2',
        'paid_at'     => 'datetime',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
