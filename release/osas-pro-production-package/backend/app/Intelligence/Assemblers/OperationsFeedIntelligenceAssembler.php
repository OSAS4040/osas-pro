<?php

declare(strict_types=1);

namespace App\Intelligence\Assemblers;

use App\Intelligence\Rules\HealthRules;
use App\Intelligence\Services\AttentionEngine;

/**
 * Global operations feed — summary-only intelligence (read-only).
 */
final class OperationsFeedIntelligenceAssembler
{
    public function __construct(
        private readonly AttentionEngine $attentionEngine,
    ) {}

    /**
     * @param  array<string, int>  $summary
     * @return array<string, mixed>
     */
    public function assemble(array $summary, bool $financialIncluded): array
    {
        $health = HealthRules::operationsFeedHealth($summary);
        $attentionDtos = $this->attentionEngine->forOperationsFeed($summary, $health->healthStatus, $financialIncluded);
        $attention = array_map(static fn ($d) => $d->toArray(), $attentionDtos);

        $total = (int) ($summary['total_items_in_window'] ?? 0);
        $wo = (int) ($summary['work_orders_count'] ?? 0);
        $activityLevel = 'none';
        if ($total >= 40) {
            $activityLevel = 'high';
        } elseif ($total >= 12) {
            $activityLevel = 'medium';
        } elseif ($total > 0) {
            $activityLevel = 'low';
        }

        $engagement = 'disengaged';
        if ($wo >= 20) {
            $engagement = 'engaged';
        } elseif ($wo >= 5) {
            $engagement = 'neutral';
        }

        return [
            'health_status' => $health->healthStatus,
            'indicators' => [
                'activity_level' => $activityLevel,
                'engagement_level' => $engagement,
                'payment_behavior' => 'unknown',
            ],
            'attention_items' => $attention,
        ];
    }
}
