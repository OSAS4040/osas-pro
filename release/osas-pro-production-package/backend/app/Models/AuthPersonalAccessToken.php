<?php

declare(strict_types=1);

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Extended Sanctum token with session visibility metadata (WAVE 1 / PR3).
 */
class AuthPersonalAccessToken extends SanctumPersonalAccessToken
{
    /** Must match Sanctum schema; Laravel would otherwise infer `auth_personal_access_tokens`. */
    protected $table = 'personal_access_tokens';

    protected $fillable = [
        'name', 'token', 'abilities', 'expires_at', 'tokenable_id', 'tokenable_type',
        'auth_channel', 'ip_address', 'user_agent', 'user_agent_summary',
    ];
}
