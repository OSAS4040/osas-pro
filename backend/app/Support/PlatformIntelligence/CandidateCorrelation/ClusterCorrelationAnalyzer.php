<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateCorrelation;

use App\Support\PlatformIntelligence\CandidateEngine\CandidateEligibilityEvaluator;
use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;

final class ClusterCorrelationAnalyzer
{
    /**
     * Count of correlation keys present in every member of the cluster (empty if any member has none of the keys).
     *
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function sharedCorrelationKeyCount(array $cluster): int
    {
        if ($cluster === []) {
            return 0;
        }

        $eval = new CandidateEligibilityEvaluator;
        $first = $this->keySet($eval->nonEmptyCorrelationKeys($cluster[0]));
        if ($first === []) {
            return 0;
        }

        $inter = $first;
        for ($i = 1, $n = count($cluster); $i < $n; $i++) {
            $next = $this->keySet($eval->nonEmptyCorrelationKeys($cluster[$i]));
            $inter = array_intersect_key($inter, $next);
            if ($inter === []) {
                return 0;
            }
        }

        return count($inter);
    }

    /**
     * @return array<string, true>
     */
    private function keySet(array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = true;
        }

        return $out;
    }
}
