<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformPricingRequestLine extends Model
{
    protected $table = 'platform_pricing_request_lines';

    protected $fillable = [
        'platform_pricing_request_id',
        'service_code',
        'tenant_service_id',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PlatformPricingRequest::class, 'platform_pricing_request_id');
    }
}
