<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Employee extends Model
{
    use HasTenantScope, SoftDeletes;

    protected $fillable = [
        'uuid', 'company_id', 'branch_id', 'user_id', 'employee_number',
        'name', 'phone', 'email', 'national_id', 'position', 'department',
        'hire_date', 'termination_date', 'base_salary', 'status',
        'skills', 'device_id', 'hr_integrations', 'internal_notes',
    ];

    protected $casts = [
        'skills'           => 'array',
        'hr_integrations'  => 'array',
        'base_salary'      => 'decimal:2',
        'hire_date'        => 'date',
        'termination_date' => 'date',
    ];

    /**
     * Mirrors `name` so workshop UIs using `full_name` display correctly in lists.
     */
    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return (string) ($this->name ?? '');
    }

    protected static function booted(): void
    {
        static::creating(function ($m) {
            $m->uuid ??= (string) Str::uuid();
            if (!$m->employee_number) {
                $count = self::where('company_id', $m->company_id)->withTrashed()->count() + 1;
                $m->employee_number = 'EMP-' . str_pad($count, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function branch(): BelongsTo { return $this->belongsTo(Branch::class); }
    public function tasks(): HasMany    { return $this->hasMany(Task::class, 'assigned_to'); }
    public function commissions(): HasMany { return $this->hasMany(Commission::class); }
    public function attendance(): HasMany  { return $this->hasMany(AttendanceLog::class); }

    public function isActive(): bool { return $this->status === 'active'; }
}
