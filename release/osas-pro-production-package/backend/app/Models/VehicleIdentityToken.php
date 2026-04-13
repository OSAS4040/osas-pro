<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleIdentityToken extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_REPLACED = 'replaced';

    protected $fillable = [
        'vehicle_id',
        'company_id',
        'token',
        'public_code',
        'status',
        'revoked_at',
        'replaced_by_id',
    ];

    protected function casts(): array
    {
        return [
            'revoked_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function replacedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function publicUrl(): string
    {
        $base = rtrim((string) config('app_urls.public_base', ''), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        return $base.'/v/'.$this->token;
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
