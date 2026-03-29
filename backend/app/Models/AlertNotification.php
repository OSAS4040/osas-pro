<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlertNotification extends Model
{
    protected $fillable = [
        'company_id', 'code', 'severity', 'subject_type', 'subject_id',
        'message', 'meta', 'is_read', 'user_id', 'read_at',
    ];

    protected $casts = [
        'meta'     => 'array',
        'is_read'  => 'boolean',
        'read_at'  => 'datetime',
    ];
}
