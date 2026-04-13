<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AlertRule extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'code', 'channel', 'condition', 'recipients', 'is_active',
    ];

    protected $casts = [
        'condition'  => 'array',
        'recipients' => 'array',
        'is_active'  => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }
}
