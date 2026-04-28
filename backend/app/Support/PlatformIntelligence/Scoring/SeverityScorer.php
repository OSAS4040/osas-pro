<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Scoring;

use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;
use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;

/**
 * Deterministic severity from evidence — documented weights (no randomness).
 *
 * Base rank: info=0, low=1, medium=2, high=3, critical=4
 */
final class SeverityScorer
{
    public function score(SignalDraft $draft): PlatformIntelligenceSeverity
    {
        $rank = match ($draft->signal_type) {
            PlatformSignalType::Composite => 2,
            PlatformSignalType::Correlation => 2,
            PlatformSignalType::MetricThreshold => 2,
            PlatformSignalType::Trend => 1,
            PlatformSignalType::Anomaly => 3,
            PlatformSignalType::Rule => 2,
            PlatformSignalType::Manual => 1,
        };

        $companyCount = count($draft->affected_company_ids);
        if ($companyCount >= 20) {
            $rank++;
        } elseif ($companyCount >= 8) {
            $rank += 0.5;
        }

        $supporting = (int) ($draft->evidence['supporting_factor_count'] ?? 0);
        if ($supporting >= 3) {
            $rank += 0.5;
        }

        if (($draft->evidence['health_critical'] ?? false) === true) {
            $rank += 1.5;
        }
        if (($draft->evidence['queue_pressure'] ?? false) === true) {
            $rank += 1.0;
        }
        if (($draft->evidence['governance_backlog'] ?? false) === true) {
            $rank += 0.5;
        }

        $daysSinceActivity = (int) ($draft->evidence['worst_last_activity_days'] ?? 0);
        if ($daysSinceActivity >= 30) {
            $rank += 0.5;
        }

        if ($draft->source === PlatformSignalSourceType::System && ($draft->evidence['scheduler_stale'] ?? false)) {
            $rank += 0.5;
        }

        return $this->clampRank($rank);
    }

    private function clampRank(float $rank): PlatformIntelligenceSeverity
    {
        $r = (int) round($rank);
        $r = max(0, min(4, $r));

        return match ($r) {
            0 => PlatformIntelligenceSeverity::Info,
            1 => PlatformIntelligenceSeverity::Low,
            2 => PlatformIntelligenceSeverity::Medium,
            3 => PlatformIntelligenceSeverity::High,
            default => PlatformIntelligenceSeverity::Critical,
        };
    }
}
