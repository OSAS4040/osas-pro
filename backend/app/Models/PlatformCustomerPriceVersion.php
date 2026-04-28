<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class PlatformCustomerPriceVersion extends Model
{
    protected $table = 'platform_customer_price_versions';

    protected $fillable = [
        'uuid',
        'company_id',
        'customer_id',
        'contract_id',
        'root_contract_id',
        'platform_pricing_request_id',
        'version_no',
        'is_reference',
        'sell_snapshot',
        'activated_at',
    ];

    protected function casts(): array
    {
        return [
            'sell_snapshot' => 'array',
            'is_reference' => 'boolean',
            'activated_at' => 'datetime',
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

        static::updating(function (self $m): void {
            if ($m->getOriginal('is_reference') && $m->isDirty(['sell_snapshot', 'version_no', 'is_reference', 'contract_id', 'root_contract_id'])) {
                throw new \DomainException('لا يمكن تعديل سعر معتمد مباشرة');
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

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function pricingRequest(): BelongsTo
    {
        return $this->belongsTo(PlatformPricingRequest::class, 'platform_pricing_request_id');
    }
}
