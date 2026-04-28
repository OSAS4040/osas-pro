<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PlatformIntelligence\Contracts\PlatformControlledActionContract;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionArtifactType;
use App\Support\PlatformIntelligence\ControlledActions\ControlledActionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $action_id
 * @property string $incident_key
 * @property string $action_type
 * @property string $action_summary
 * @property int|null $actor_user_id
 * @property string $status
 * @property string|null $assigned_owner
 * @property bool $follow_up_required
 * @property \Illuminate\Support\Carbon|null $scheduled_for
 * @property string|null $linked_decision_id
 * @property string|null $linked_notes
 * @property string|null $external_reference
 * @property string|null $idempotency_key
 * @property string|null $completion_reason
 * @property string|null $canceled_reason
 */
final class PlatformControlledAction extends Model
{
    protected $table = 'platform_controlled_actions';

    protected $fillable = [
        'action_id', 'incident_key', 'action_type', 'action_summary', 'actor_user_id',
        'status', 'assigned_owner', 'follow_up_required', 'scheduled_for',
        'linked_decision_id', 'linked_notes', 'external_reference', 'idempotency_key',
        'completion_reason', 'canceled_reason',
    ];

    protected function casts(): array
    {
        return [
            'follow_up_required' => 'boolean',
            'scheduled_for' => 'datetime',
        ];
    }

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function toContract(): PlatformControlledActionContract
    {
        $actor = $this->actor_user_id !== null ? 'user:'.$this->actor_user_id : 'system';

        return new PlatformControlledActionContract(
            action_id: (string) $this->action_id,
            incident_key: (string) $this->incident_key,
            action_type: (string) $this->action_type,
            action_summary: (string) $this->action_summary,
            actor: $actor,
            created_at: $this->created_at?->toIso8601String() ?? '',
            status: (string) $this->status,
            assigned_owner: $this->assigned_owner,
            follow_up_required: (bool) $this->follow_up_required,
            scheduled_for: $this->scheduled_for?->toIso8601String(),
            linked_decision_id: $this->linked_decision_id,
            linked_notes: $this->linked_notes,
            external_reference: $this->external_reference,
            completion_reason: $this->completion_reason,
            canceled_reason: $this->canceled_reason,
        );
    }

    public function artifactType(): ControlledActionArtifactType
    {
        return ControlledActionArtifactType::from((string) $this->action_type);
    }

    public function actionStatus(): ControlledActionStatus
    {
        return ControlledActionStatus::from((string) $this->status);
    }
}
