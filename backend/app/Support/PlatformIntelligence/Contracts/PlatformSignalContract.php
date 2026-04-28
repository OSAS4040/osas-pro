<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Contracts;

use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use DateTimeImmutable;

/**
 * Observable platform intelligence signal — not an incident and not a decision.
 */
final readonly class PlatformSignalContract
{
    /**
     * @param  list<string>  $affected_entities
     * @param  list<int|string>  $affected_companies
     * @param  list<string>  $correlation_keys
     */
    public function __construct(
        public string $signal_key,
        public PlatformSignalType $signal_type,
        public string $title,
        public string $summary,
        public string $why_summary,
        public PlatformIntelligenceSeverity $severity,
        public float $confidence,
        public PlatformSignalSourceType $source,
        public ?string $source_ref,
        public string $affected_scope,
        public array $affected_entities,
        public array $affected_companies,
        public DateTimeImmutable $first_seen_at,
        public DateTimeImmutable $last_seen_at,
        public string $recommended_next_step,
        public array $correlation_keys,
        public ?string $trace_id,
        public ?string $correlation_id,
    ) {}
}
