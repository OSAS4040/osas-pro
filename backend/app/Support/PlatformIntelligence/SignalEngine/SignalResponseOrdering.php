<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Stable ordering for API/SPA lists — avoids visual "jumping" between refreshes.
 */
final class SignalResponseOrdering
{
    /**
     * @param  list<PlatformSignalContract>  $signals
     * @return list<PlatformSignalContract>
     */
    public static function sortStable(array $signals): array
    {
        usort($signals, static function (PlatformSignalContract $a, PlatformSignalContract $b): int {
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

            return strcmp($a->signal_key, $b->signal_key);
        });

        return array_values($signals);
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
