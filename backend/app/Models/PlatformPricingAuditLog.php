<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PlatformPricingAuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'platform_pricing_audit_logs';

    protected $fillable = [
        'platform_pricing_request_id',
        'user_id',
        'action',
        'payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PlatformPricingRequest::class, 'platform_pricing_request_id');
    }
}
