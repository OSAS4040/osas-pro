<?php

declare(strict_types=1);

namespace App\Customers\Profile;

/**
 * Immutable customer operational hub payload (read-only).
 */
final readonly class CustomerProfileDto
{
    /**
     * @param  array<string, mixed>  $customer
     * @param  array<string, mixed>  $summary
     * @param  array<string, mixed>  $activitySnapshot
     * @param  array<string, mixed>  $behaviorIndicators
     * @param  array<string, mixed>  $relationships
     * @param  list<array{code: string, severity: string, message: string}>  $attentionItems
     * @param  array<string, mixed>  $intelligence
     */
    public function __construct(
        public array $customer,
        public array $summary,
        public array $activitySnapshot,
        public array $behaviorIndicators,
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
            'customer' => $this->customer,
            'summary' => $this->summary,
            'activity_snapshot' => $this->activitySnapshot,
            'behavior_indicators' => $this->behaviorIndicators,
            'relationships' => $this->relationships,
            'attention_items' => $this->attentionItems,
            'intelligence' => $this->intelligence,
        ];
    }
}
