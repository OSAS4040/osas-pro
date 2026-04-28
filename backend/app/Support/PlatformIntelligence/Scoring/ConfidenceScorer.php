<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\Scoring;

use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;
use App\Support\PlatformIntelligence\SignalEngine\Normalize\OverviewSnapshotNormalizer;

/**
 * Confidence 0..1 from input quality, evidence strength, and absence of conflict.
 */
final class ConfidenceScorer
{
    public function score(SignalDraft $draft, OverviewSnapshotNormalizer $norm): float
    {
        $base = 0.45 * $norm->overviewCompletenessScore();

        $evidenceStrength = min(0.35, 0.08 * count($draft->evidence));

        $companyCount = count($draft->affected_company_ids);
        if ($companyCount > 0) {
            $base += min(0.12, $companyCount * 0.004);
        }

        if (($draft->evidence['derived_from_attention'] ?? false) === true) {
            $base += 0.08;
        }

        if (($draft->evidence['conflict_penalty'] ?? false) === true) {
            $base -= 0.12;
        }

        if (($draft->evidence['sparse_metrics'] ?? false) === true) {
            $base -= 0.1;
        }

        return $this->clamp($base + $evidenceStrength);
    }

    private function clamp(float $v): float
    {
        if ($v < 0.12) {
            return 0.12;
        }
        if ($v > 0.96) {
            return 0.96;
        }

        return round($v, 3);
    }
}
