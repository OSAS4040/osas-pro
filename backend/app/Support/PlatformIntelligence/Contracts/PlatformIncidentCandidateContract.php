<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Contracts;

use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use DateTimeImmutable;

/**
 * Correlated grouping of signals — not yet a managed incident record.
 *
 * @param  list<string>  $source_signals
 * @param  list<string>  $affected_entities
 * @param  list<int|string>  $affected_companies
 * @param  list<string>  $recommended_actions
 */
final readonly class PlatformIncidentCandidateContract
{
    /**
     * @param  list<string>  $source_signals
     * @param  list<string>  $affected_entities
     * @param  list<int|string>  $affected_companies
     * @param  list<string>  $recommended_actions
     */
    public function __construct(
        public string $incident_key,
        public string $incident_type,
        public string $title,
        public string $summary,
        public string $why_summary,
        public PlatformIntelligenceSeverity $severity,
        public float $confidence,
        public array $source_signals,
        public string $affected_scope,
        public array $affected_entities,
        public array $affected_companies,
        public DateTimeImmutable $first_seen_at,
        public DateTimeImmutable $last_seen_at,
        public array $recommended_actions,
        public string $grouping_reason,
        public string $dedupe_fingerprint,
    ) {}
}
