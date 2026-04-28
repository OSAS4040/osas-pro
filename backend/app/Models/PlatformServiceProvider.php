<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class PlatformServiceProvider extends Model
{
    protected $table = 'platform_service_providers';

    protected $fillable = [
        'uuid', 'name', 'contact_name', 'phone', 'email', 'regions', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'regions' => 'array',
            'is_active' => 'boolean',
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

    public function costs(): HasMany
    {
        return $this->hasMany(PlatformServiceProviderCost::class, 'platform_service_provider_id');
    }
}
