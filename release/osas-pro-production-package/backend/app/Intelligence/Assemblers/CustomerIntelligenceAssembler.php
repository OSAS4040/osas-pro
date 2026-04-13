<?php

declare(strict_types=1);

namespace App\Intelligence\Assemblers;

use App\Intelligence\Services\AttentionEngine;
use App\Intelligence\Services\IntelligenceEvaluator;
use App\Intelligence\Support\LegacyAttentionAdapter;

/**
 * Customer hub intelligence block (read-only).
 */
final class CustomerIntelligenceAssembler
{
    public function __construct(
        private readonly IntelligenceEvaluator $evaluator,
        private readonly AttentionEngine $attentionEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     * @param  array<string, mixed>  $summary
     * @return array{
     *   intelligence: array<string, mixed>,
     *   attention_items_legacy: list<array{code: string, severity: string, message: string}>,
     *   behavior_indicators: array<string, mixed>
     * }
     */
    public function assemble(array $raw, array $summary, bool $includeFinancial): array
    {
        $lastIso = isset($summary['last_activity_at']) && is_string($summary['last_activity_at'])
            ? $summary['last_activity_at']
            : null;

        $eval = $this->evaluator->evaluateCustomer($raw, $includeFinancial, $lastIso);
        $inactivityFlag = (bool) ($eval['inactivity_flag'] ?? false);
        unset($eval['inactivity_flag']);

        $attentionDtos = $this->attentionEngine->forCustomer(
            $raw,
            $eval['indicators'],
            $inactivityFlag,
            $includeFinancial,
        );
        $attention = array_map(static fn ($d) => $d->toArray(), $attentionDtos);

        $ind = $eval['indicators'];
        $behaviorIndicators = [
            'activity_level' => ($ind['activity_level'] ?? 'low') === 'none' ? 'inactive' : $ind['activity_level'],
            'payment_behavior' => $ind['payment_behavior'] ?? 'unknown',
            'engagement_level' => match ($ind['engagement_level'] ?? 'disengaged') {
                'engaged' => 'engaged',
                'neutral' => 'moderate',
                default => 'none',
            },
            'inactivity_flag' => $inactivityFlag,
        ];

        $intelligence = [
            'health_status' => $eval['health_status'],
            'indicators' => [
                'activity_level' => $ind['activity_level'] ?? 'low',
                'engagement_level' => $ind['engagement_level'] ?? 'disengaged',
                'payment_behavior' => $ind['payment_behavior'] ?? 'unknown',
            ],
            'attention_items' => $attention,
        ];

        return [
            'intelligence' => $intelligence,
            'attention_items_legacy' => LegacyAttentionAdapter::toLegacy($attention),
            'behavior_indicators' => $behaviorIndicators,
        ];
    }
}
