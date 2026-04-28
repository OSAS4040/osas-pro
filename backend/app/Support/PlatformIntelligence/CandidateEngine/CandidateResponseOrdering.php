<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

final class CandidateResponseOrdering
{
    /**
     * @param  list<PlatformIncidentCandidateContract>  $rows
     * @return list<PlatformIncidentCandidateContract>
     */
    public static function sortStable(array $rows): array
    {
        usort($rows, static function (PlatformIncidentCandidateContract $a, PlatformIncidentCandidateContract $b): int {
            $ra = self::severityRank($a->severity);
            $rb = self::severityRank($b->severity);
            if ($ra !== $rb) {
                return $rb <=> $ra;
            }
            if ($a->confidence !== $b->confidence) {
                return $b->confidence <=> $a->confidence;
            }
            $ta = $a->last_seen_at->getTimestamp();
            $tb = $b->last_seen_at->getTimestamp();
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            return strcmp($a->incident_key, $b->incident_key);
        });

        return array_values($rows);
    }

    private static function severityRank(PlatformIntelligenceSeverity $s): int
    {
        return match ($s) {
            PlatformIntelligenceSeverity::Critical => 5,
            PlatformIntelligenceSeverity::High => 4,
            PlatformIntelligenceSeverity::Medium => 3,
            PlatformIntelligenceSeverity::Low => 2,
            PlatformIntelligenceSeverity::Info => 1,
        };
    }
}
