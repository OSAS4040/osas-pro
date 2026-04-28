<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\IncidentSerialization;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use DateTimeInterface;

final class PlatformIncidentContractSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PlatformIncidentContract $i): array
    {
        return [
            'incident_key' => $i->incident_key,
            'incident_type' => $i->incident_type,
            'title' => $i->title,
            'summary' => $i->summary,
            'why_summary' => $i->why_summary,
            'severity' => $i->severity->value,
            'confidence' => $i->confidence,
            'status' => $i->status->value,
            'owner' => $i->owner,
            'ownership_state' => $i->ownership_state->value,
            'escalation_state' => $i->escalation_state->value,
            'affected_scope' => $i->affected_scope,
            'affected_entities' => $i->affected_entities,
            'affected_companies' => $i->affected_companies,
            'source_signals' => $i->source_signals,
            'recommended_actions' => $i->recommended_actions,
            'first_seen_at' => $i->first_seen_at->format(DateTimeInterface::ATOM),
            'last_seen_at' => $i->last_seen_at->format(DateTimeInterface::ATOM),
            'acknowledged_at' => $i->acknowledged_at?->format(DateTimeInterface::ATOM),
            'resolved_at' => $i->resolved_at?->format(DateTimeInterface::ATOM),
            'closed_at' => $i->closed_at?->format(DateTimeInterface::ATOM),
            'last_status_change_at' => $i->last_status_change_at?->format(DateTimeInterface::ATOM),
            'resolve_reason' => $i->resolve_reason,
            'close_reason' => $i->close_reason,
        ];
    }
}
