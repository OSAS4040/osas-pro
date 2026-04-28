<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Trace;

use DateTimeImmutable;

/**
 * Append-only trace unit for intelligence domain (read-model / audit bridge).
 */
final readonly class PlatformIntelligenceTraceEvent
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public PlatformIntelligenceTraceEventType $event_type,
        public string $actor,
        public DateTimeImmutable $timestamp,
        public string $source,
        public string $reason,
        public ?string $correlation_id,
        public ?string $trace_id,
        public string $linked_entity_key,
        public array $context = [],
    ) {}
}
