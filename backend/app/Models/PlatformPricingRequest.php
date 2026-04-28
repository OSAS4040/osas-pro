<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PlatformPricingRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class PlatformPricingRequest extends Model
{
    protected $table = 'platform_pricing_requests';

    protected $fillable = [
        'uuid', 'company_id', 'customer_id', 'status', 'title', 'vehicle_types',
        'created_by_user_id', 'reviewed_by_user_id', 'reviewed_at', 'review_completed_at',
        'review_payload', 'escalated_by_user_id', 'escalated_at', 'approved_by_user_id',
        'approved_at', 'rejection_reason', 'root_pricing_request_id', 'version_no',
    ];

    protected function casts(): array
    {
        return [
            'vehicle_types' => 'array',
            'review_payload' => 'array',
            'reviewed_at' => 'datetime',
            'review_completed_at' => 'datetime',
            'escalated_at' => 'datetime',
            'approved_at' => 'datetime',
            'status' => PlatformPricingRequestStatus::class,
            'version_no' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            if ($m->uuid === null || $m->uuid === '') {
                $m->uuid = (string) Str::uuid();
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PlatformPricingRequestLine::class, 'platform_pricing_request_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(PlatformPricingAuditLog::class, 'platform_pricing_request_id')
            ->orderBy('created_at');
    }
}
