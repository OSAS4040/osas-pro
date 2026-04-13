<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthLoginEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auth_login_events';

    protected $fillable = [
        'user_id', 'company_id', 'event', 'auth_channel', 'reason_code',
        'token_id', 'ip_address', 'user_agent_summary', 'trace_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
