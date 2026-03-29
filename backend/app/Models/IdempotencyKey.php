<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    protected $fillable = [
        'company_id', 'key', 'endpoint', 'trace_id',
        'request_hash', 'response_snapshot', 'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];
}
