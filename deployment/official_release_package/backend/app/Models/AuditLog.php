<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = true;
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'user_id', 'action',
        'subject_type', 'subject_id', 'before', 'after',
        'ip_address', 'user_agent', 'trace_id',
    ];

    protected $casts = [
        'before' => 'array',
        'after'  => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }
}
