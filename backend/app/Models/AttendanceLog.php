<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    protected $fillable = [
        'company_id', 'branch_id', 'employee_id', 'type',
        'logged_at', 'latitude', 'longitude',
        'device_id', 'ip_address', 'is_valid', 'invalidation_reason',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
        'is_valid'  => 'boolean',
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function employee(): BelongsTo { return $this->belongsTo(Employee::class); }
}
