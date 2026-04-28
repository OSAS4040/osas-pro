<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\DecisionSerialization;

use App\Support\PlatformIntelligence\Contracts\PlatformDecisionLogEntryContract;

final class PlatformDecisionLogEntryContractSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PlatformDecisionLogEntryContract $c): array
    {
        return [
            'decision_id' => $c->decision_id,
            'incident_key' => $c->incident_key,
            'decision_type' => $c->decision_type->value,
            'decision_summary' => $c->decision_summary,
            'rationale' => $c->rationale,
            'actor' => $c->actor,
            'created_at' => $c->created_at->format(\DateTimeInterface::ATOM),
            'linked_signals' => $c->linked_signals,
            'linked_notes' => $c->linked_notes,
            'expected_outcome' => $c->expected_outcome,
            'evidence_refs' => $c->evidence_refs,
            'follow_up_required' => $c->follow_up_required,
        ];
    }
}
