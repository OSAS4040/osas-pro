<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Contracts;

use App\Support\PlatformIntelligence\Enums\PlatformIncidentEscalationState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentOwnershipState;
use App\Support\PlatformIntelligence\Enums\PlatformIncidentStatus;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use DateTimeImmutable;

/**
 * Managed incident lifecycle entity — distinct from {@see PlatformSignalContract} and {@see PlatformIncidentCandidateContract}.
 *
 * @param  list<string>  $source_signals
 * @param  list<string>  $affected_entities
 * @param  list<int|string>  $affected_companies
 * @param  list<string>  $recommended_actions
 */
final readonly class PlatformIncidentContract
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
        public PlatformIncidentStatus $status,
        public ?string $owner,
        public PlatformIncidentOwnershipState $ownership_state,
        public PlatformIncidentEscalationState $escalation_state,
        public string $affected_scope,
        public array $affected_entities,
        public array $affected_companies,
        public array $source_signals,
        public array $recommended_actions,
        public DateTimeImmutable $first_seen_at,
        public DateTimeImmutable $last_seen_at,
        public ?DateTimeImmutable $acknowledged_at,
        public ?DateTimeImmutable $resolved_at,
        public ?DateTimeImmutable $closed_at,
        public ?DateTimeImmutable $last_status_change_at,
        public ?string $resolve_reason = null,
        public ?string $close_reason = null,
    ) {}
}
