<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $incident_key
 * @property int|null $actor_user_id
 * @property string $event_type
 * @property string|null $prior_status
 * @property string|null $next_status
 * @property string|null $prior_escalation_state
 * @property string|null $next_escalation_state
 * @property string|null $prior_owner
 * @property string|null $next_owner
 * @property string|null $reason
 * @property array<string, mixed>|null $context
 */
final class PlatformIncidentLifecycleEvent extends Model
{
    public $timestamps = false;

    protected $table = 'platform_incident_lifecycle_events';

    protected $fillable = [
        'incident_key', 'actor_user_id', 'event_type',
        'prior_status', 'next_status',
        'prior_escalation_state', 'next_escalation_state',
        'prior_owner', 'next_owner',
        'reason', 'context', 'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, self>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
