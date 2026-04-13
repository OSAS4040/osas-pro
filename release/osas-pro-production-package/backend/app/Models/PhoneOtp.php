<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneOtp extends Model
{
    protected $fillable = [
        'phone',
        'otp_code_hash',
        'purpose',
        'expires_at',
        'verified_at',
        'attempts_count',
        'max_attempts',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
