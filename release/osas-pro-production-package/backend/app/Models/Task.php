<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Task extends Model
{
    use HasTenantScope;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'work_order_id',
        'title', 'description', 'type', 'status', 'priority',
        'assigned_to', 'assigned_by', 'due_at',
        'started_at', 'completed_at',
        'estimated_minutes', 'actual_minutes', 'completion_notes',
    ];

    protected $casts = [
        'due_at'       => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(fn ($m) => $m->uuid ??= (string) Str::uuid());
    }

    public function employee(): BelongsTo  { return $this->belongsTo(Employee::class, 'assigned_to'); }
    public function workOrder(): BelongsTo { return $this->belongsTo(WorkOrder::class); }
    public function assigner(): BelongsTo  { return $this->belongsTo(User::class, 'assigned_by'); }

    public function isOverdue(): bool
    {
        return $this->due_at && $this->due_at->isPast() && $this->status !== 'completed';
    }
}
