<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'company_id', 'name', 'name_ar', 'symbol', 'symbol_ar',
        'type', 'is_base', 'is_active', 'is_system',
    ];

    protected $casts = [
        'is_base'   => 'boolean',
        'is_active' => 'boolean',
        'is_system' => 'boolean',
    ];

    public function fromConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'from_unit_id');
    }

    public function toConversions(): HasMany
    {
        return $this->hasMany(UnitConversion::class, 'to_unit_id');
    }

    public function convertTo(Unit $targetUnit, float $quantity): float
    {
        if ($this->id === $targetUnit->id) {
            return $quantity;
        }

        $conversion = $this->fromConversions()
            ->where('to_unit_id', $targetUnit->id)
            ->where('is_active', true)
            ->first();

        if ($conversion) {
            return round($quantity * (float) $conversion->factor, 8);
        }

        $reverseConversion = $this->toConversions()
            ->where('from_unit_id', $targetUnit->id)
            ->where('is_active', true)
            ->first();

        if ($reverseConversion) {
            return round($quantity / (float) $reverseConversion->factor, 8);
        }

        throw new \DomainException(
            "No conversion found from [{$this->symbol}] to [{$targetUnit->symbol}]."
        );
    }
}
