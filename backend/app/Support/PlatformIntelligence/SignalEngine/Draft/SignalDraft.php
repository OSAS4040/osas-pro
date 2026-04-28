<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Draft;

use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use DateTimeImmutable;

/**
 * Internal pipeline shape only — never exposed as API DTO.
 *
 * @param  list<string>  $affected_entities
 * @param  list<int>  $affected_company_ids
 * @param  list<string>  $correlation_keys
 * @param  array<string, int|float|string|bool>  $evidence
 */
final readonly class SignalDraft
{
    /**
     * @param  list<string>  $affected_entities
     * @param  list<int>  $affected_company_ids
     * @param  list<string>  $correlation_keys
     * @param  array<string, int|float|string|bool>  $evidence
     */
    public function __construct(
        public string $draft_key,
        public PlatformSignalType $signal_type,
        public PlatformSignalSourceType $source,
        public string $title,
        public string $summary_stub,
        public string $why_stub,
        public string $affected_scope,
        public array $affected_entities,
        public array $affected_company_ids,
        public array $correlation_keys,
        public array $evidence,
        public ?string $source_ref,
        public DateTimeImmutable $observed_at,
    ) {}
}
