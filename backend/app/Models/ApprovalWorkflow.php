<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApprovalWorkflow extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'subject_type', 'subject_id', 'policy_code',
        'status', 'requested_by', 'assigned_approver', 'resolved_by',
        'resolved_at', 'acted_at', 'requester_note', 'resolver_note', 'trace_id',
        'current_step', 'total_steps', 'meta',
    ];

    protected $casts = [
        'meta'        => 'array',
        'resolved_at' => 'datetime',
        'acted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
