<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateSerialization;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use DateTimeInterface;

final class PlatformIncidentCandidateContractSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PlatformIncidentCandidateContract $c): array
    {
        return [
            'incident_key' => $c->incident_key,
            'incident_type' => $c->incident_type,
            'title' => $c->title,
            'summary' => $c->summary,
            'why_summary' => $c->why_summary,
            'severity' => $c->severity->value,
            'confidence' => $c->confidence,
            'source_signals' => $c->source_signals,
            'affected_scope' => $c->affected_scope,
            'affected_entities' => $c->affected_entities,
            'affected_companies' => $c->affected_companies,
            'first_seen_at' => $c->first_seen_at->format(DateTimeInterface::ATOM),
            'last_seen_at' => $c->last_seen_at->format(DateTimeInterface::ATOM),
            'recommended_actions' => $c->recommended_actions,
            'grouping_reason' => $c->grouping_reason,
            'dedupe_fingerprint' => $c->dedupe_fingerprint,
        ];
    }
}
