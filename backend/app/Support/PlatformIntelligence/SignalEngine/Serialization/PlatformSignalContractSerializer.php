<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Serialization;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use DateTimeInterface;

final class PlatformSignalContractSerializer
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(PlatformSignalContract $s): array
    {
        return [
            'signal_key' => $s->signal_key,
            'signal_type' => $s->signal_type->value,
            'title' => $s->title,
            'summary' => $s->summary,
            'why_summary' => $s->why_summary,
            'severity' => $s->severity->value,
            'confidence' => $s->confidence,
            'source' => $s->source->value,
            'source_ref' => $s->source_ref,
            'affected_scope' => $s->affected_scope,
            'affected_entities' => $s->affected_entities,
            'affected_companies' => $s->affected_companies,
            'first_seen_at' => $s->first_seen_at->format(DateTimeInterface::ATOM),
            'last_seen_at' => $s->last_seen_at->format(DateTimeInterface::ATOM),
            'recommended_next_step' => $s->recommended_next_step,
            'correlation_keys' => $s->correlation_keys,
            'trace_id' => $s->trace_id,
            'correlation_id' => $s->correlation_id,
        ];
    }
}
