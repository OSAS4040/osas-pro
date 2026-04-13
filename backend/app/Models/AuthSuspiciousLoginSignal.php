<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Append-only suspicious auth telemetry (PR5). Read-only operational visibility.
 *
 * @property int $id
 * @property string $signal_type
 * @property string|null $channel
 * @property string $subject_fingerprint
 * @property string|null $ip_address
 * @property string|null $user_agent_hash
 * @property string|null $trace_id
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon $created_at
 */
class AuthSuspiciousLoginSignal extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auth_suspicious_login_signals';

    /** @var list<string> */
    protected $fillable = [
        'signal_type',
        'channel',
        'subject_fingerprint',
        'ip_address',
        'user_agent_hash',
        'trace_id',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
