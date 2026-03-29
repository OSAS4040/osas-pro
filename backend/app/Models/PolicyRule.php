<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PolicyRule extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'code', 'entity_type', 'entity_id',
        'operator', 'value', 'action', 'is_active', 'created_by',
    ];

    protected $casts = [
        'value'     => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }
}
