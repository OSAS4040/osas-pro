<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\ControlledActions;

use App\Support\PlatformIntelligence\Contracts\PlatformControlledActionContract;

final class PlatformControlledActionContractSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PlatformControlledActionContract $c): array
    {
        return [
            'action_id' => $c->action_id,
            'incident_key' => $c->incident_key,
            'action_type' => $c->action_type,
            'action_summary' => $c->action_summary,
            'actor' => $c->actor,
            'created_at' => $c->created_at,
            'status' => $c->status,
            'assigned_owner' => $c->assigned_owner,
            'follow_up_required' => $c->follow_up_required,
            'scheduled_for' => $c->scheduled_for,
            'linked_decision_id' => $c->linked_decision_id,
            'linked_notes' => $c->linked_notes,
            'external_reference' => $c->external_reference,
            'completion_reason' => $c->completion_reason,
            'canceled_reason' => $c->canceled_reason,
        ];
    }
}
