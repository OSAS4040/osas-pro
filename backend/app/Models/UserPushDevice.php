<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'fcm_token',
        'device_name',
        'device_type',
        'last_registered_at',
    ];

    protected $casts = [
        'last_registered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
