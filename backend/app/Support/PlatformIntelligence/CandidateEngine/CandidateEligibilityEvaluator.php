<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Defines which {@see PlatformSignalContract} rows may enter candidate correlation.
 *
 * @see docs/Platform_Intelligence_Incident_Candidate_Layer.md
 */
final class CandidateEligibilityEvaluator
{
    /** Floor for any non-info severity path. */
    public const MIN_CONFIDENCE = 0.38;

    /** Stricter floor when severity is info (reduces single-shot noise). */
    public const INFO_MIN_CONFIDENCE = 0.52;

    public function isEligible(PlatformSignalContract $s): bool
    {
        if ($s->confidence < self::MIN_CONFIDENCE) {
            return false;
        }

        $hasCompanies = $s->affected_companies !== [];
        $hasEntities = $s->affected_entities !== [];
        $hasCorrelation = $this->nonEmptyCorrelationKeys($s) !== [];

        if (! $hasCompanies && ! $hasEntities && ! $hasCorrelation) {
            return false;
        }

        if ($s->severity === PlatformIntelligenceSeverity::Info) {
            if (! $hasCorrelation) {
                return false;
            }
            if ($s->confidence < self::INFO_MIN_CONFIDENCE) {
                return false;
            }
            if (! $hasCompanies && ! $hasEntities) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public function nonEmptyCorrelationKeys(PlatformSignalContract $s): array
    {
        $out = [];
        foreach ($s->correlation_keys as $k) {
            $t = trim((string) $k);
            if ($t !== '') {
                $out[$t] = true;
            }
        }

        return array_keys($out);
    }
}
