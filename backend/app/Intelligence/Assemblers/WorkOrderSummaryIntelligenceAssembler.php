<?php

declare(strict_types=1);

namespace App\Intelligence\Assemblers;

use App\Intelligence\Rules\HealthRules;
use App\Intelligence\Services\AttentionEngine;

/**
 * Work-order operational summary report — lightweight intelligence.
 */
final class WorkOrderSummaryIntelligenceAssembler
{
    public function __construct(
        private readonly AttentionEngine $attentionEngine,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function assemble(array $rows): array
    {
        $total = array_sum(array_column($rows, 'count'));
        $health = HealthRules::workOrderSummaryHealth($rows, (int) $total);
        $attentionDtos = $this->attentionEngine->forWorkOrderSummary($rows, (int) $total, $health->healthStatus);
        $attention = array_map(static fn ($d) => $d->toArray(), $attentionDtos);

        $activityLevel = 'none';
        if ($total >= 30) {
            $activityLevel = 'high';
        } elseif ($total >= 10) {
            $activityLevel = 'medium';
        } elseif ($total > 0) {
            $activityLevel = 'low';
        }

        return [
            'health_status' => $health->healthStatus,
            'indicators' => [
                'activity_level' => $activityLevel,
                'engagement_level' => $total >= 15 ? 'engaged' : ($total >= 3 ? 'neutral' : 'disengaged'),
                'payment_behavior' => 'unknown',
            ],
            'attention_items' => $attention,
        ];
    }
}
