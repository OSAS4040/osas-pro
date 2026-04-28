<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformServiceProviderCost extends Model
{
    protected $table = 'platform_service_provider_costs';

    protected $fillable = [
        'platform_service_provider_id',
        'service_code',
        'cost_amount',
        'currency',
        'effective_from',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_amount' => 'decimal:4',
            'effective_from' => 'date',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(PlatformServiceProvider::class, 'platform_service_provider_id');
    }
}
