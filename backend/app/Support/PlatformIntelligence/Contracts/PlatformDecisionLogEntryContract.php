<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Contracts;

use App\Support\PlatformIntelligence\Enums\PlatformDecisionType;
use DateTimeImmutable;

/**
 * Immutable record of human/system rationale — not executable "Action" payloads.
 *
 * @param  list<string>  $linked_signals
 * @param  list<string>  $linked_notes
 * @param  list<string>  $evidence_refs
 */
final readonly class PlatformDecisionLogEntryContract
{
    /**
     * @param  list<string>  $linked_signals
     * @param  list<string>  $linked_notes
     * @param  list<string>  $evidence_refs
     */
    public function __construct(
        public string $decision_id,
        public string $incident_key,
        public PlatformDecisionType $decision_type,
        public string $decision_summary,
        public string $rationale,
        public string $actor,
        public DateTimeImmutable $created_at,
        public array $linked_signals,
        public array $linked_notes,
        public string $expected_outcome,
        public array $evidence_refs,
        public bool $follow_up_required,
    ) {}
}
