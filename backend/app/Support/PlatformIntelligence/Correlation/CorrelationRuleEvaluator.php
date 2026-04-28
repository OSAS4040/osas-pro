<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Correlation;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Contracts\PlatformIncidentContract;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\CorrelationRelationType;

/**
 * Documented, testable correlation rules — no undocumented heuristics.
 */
final class CorrelationRuleEvaluator
{
    /**
     * @return array{relation_type: string, relation_reason: string, compact_why: string}|null
     */
    public static function signalToIncident(PlatformSignalContract $signal, PlatformIncidentContract $incident): ?array
    {
        if (in_array($signal->signal_key, $incident->source_signals, true)) {
            return [
                'relation_type' => CorrelationRelationType::Causal->value,
                'relation_reason' => 'signal_key_listed_on_incident_source_signals',
                'compact_why' => 'الإشارة مدرجة كمصدر مباشر للحادث.',
            ];
        }

        if (self::companyOverlap($signal->affected_companies, $incident->affected_companies)
            && (count($signal->correlation_keys) > 0 && self::stringListIntersects($signal->correlation_keys, $incident->source_signals))) {
            return [
                'relation_type' => CorrelationRelationType::Contextual->value,
                'relation_reason' => 'shared_companies_and_signal_correlation_key_overlaps_incident_signal_refs',
                'compact_why' => 'تقاطع شركات مع تقاطع جزئي بين مفاتيح الارتباط وإشارات الحادث.',
            ];
        }

        if (self::companyOverlap($signal->affected_companies, $incident->affected_companies)) {
            return [
                'relation_type' => CorrelationRelationType::Contextual->value,
                'relation_reason' => 'shared_affected_companies_only',
                'compact_why' => 'نفس الشركات المتأثرة — سياق تشغيلي فقط.',
            ];
        }

        return null;
    }

    /**
     * @return array{relation_type: string, relation_reason: string, compact_why: string}|null
     */
    public static function candidateToIncident(PlatformIncidentCandidateContract $candidate, PlatformIncidentContract $incident): ?array
    {
        if ($candidate->incident_key === $incident->incident_key) {
            return [
                'relation_type' => CorrelationRelationType::Derived->value,
                'relation_reason' => 'candidate_incident_key_equals_materialized_incident',
                'compact_why' => 'المرشح يحمل نفس مفتاح الحادث الممثّل.',
            ];
        }

        return null;
    }

    /**
     * @param  list<int|string>  $a
     * @param  list<int|string>  $b
     */
    private static function companyOverlap(array $a, array $b): bool
    {
        $sa = array_map(static fn ($x) => (string) $x, $a);
        $sb = array_map(static fn ($x) => (string) $x, $b);

        return count(array_intersect($sa, $sb)) > 0;
    }

    /**
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function stringListIntersects(array $a, array $b): bool
    {
        if ($a === [] || $b === []) {
            return false;
        }

        return count(array_intersect(array_values($a), array_values($b))) > 0;
    }
}
