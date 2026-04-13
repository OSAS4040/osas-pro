<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitConversion extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'from_unit_id', 'to_unit_id', 'factor', 'is_active',
    ];

    protected $casts = [
        'factor'    => 'decimal:8',
        'is_active' => 'boolean',
    ];

    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id');
    }

    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id');
    }
}
