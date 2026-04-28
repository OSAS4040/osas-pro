<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformPricingProviderQuote extends Model
{
    protected $table = 'platform_pricing_provider_quotes';

    protected $fillable = [
        'platform_pricing_request_id',
        'platform_service_provider_id',
        'total_provider_cost',
        'sell_price_suggested',
        'margin_suggested_pct',
        'payload',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'total_provider_cost' => 'decimal:4',
            'sell_price_suggested' => 'decimal:4',
            'margin_suggested_pct' => 'decimal:3',
            'payload' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PlatformPricingRequest::class, 'platform_pricing_request_id');
    }
}
