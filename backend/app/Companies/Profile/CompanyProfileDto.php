<?php

declare(strict_types=1);

namespace App\Companies\Profile;

/**
 * Immutable company profile hub payload (read-only).
 */
final readonly class CompanyProfileDto
{
    /**
     * @param  array<string, mixed>  $company
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $activitySnapshot
     * @param  array<string, mixed>  $healthIndicators
     * @param  array<string, mixed>  $relationships
     * @param  list<array{code: string, severity: string, message: string}>  $attentionItems
     * @param  array<string, mixed>  $intelligence
     */
    public function __construct(
        public array $company,
        public array $summary,
        public array $activitySnapshot,
        public array $healthIndicators,
        public array $relationships,
        public array $attentionItems,
        public array $intelligence,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'company' => $this->company,
            'summary' => $this->summary,
            'activity_snapshot' => $this->activitySnapshot,
            'health_indicators' => $this->healthIndicators,
            'relationships' => $this->relationships,
            'attention_items' => $this->attentionItems,
            'intelligence' => $this->intelligence,
        ];
    }
}
