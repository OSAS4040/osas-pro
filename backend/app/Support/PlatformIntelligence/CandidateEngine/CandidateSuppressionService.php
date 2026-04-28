<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformIncidentCandidateContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Post-cluster filters to drop weak or duplicate candidates.
 */
final class CandidateSuppressionService
{
    /**
     * @param  list<PlatformIncidentCandidateContract>  $candidates
     * @return list<PlatformIncidentCandidateContract>
     */
    public function suppress(array $candidates): array
    {
        $filtered = [];
        foreach ($candidates as $c) {
            if ($this->shouldDropWeak($c)) {
                continue;
            }
            $filtered[] = $c;
        }

        return $this->dedupeByFingerprint($filtered);
    }

    private function shouldDropWeak(PlatformIncidentCandidateContract $c): bool
    {
        if ($c->severity === PlatformIntelligenceSeverity::Info
            && count($c->source_signals) === 1
            && $c->affected_companies === []) {
            return true;
        }

        return false;
    }

    /**
     * @param  list<PlatformIncidentCandidateContract>  $items
     * @return list<PlatformIncidentCandidateContract>
     */
    private function dedupeByFingerprint(array $items): array
    {
        usort($items, static function (PlatformIncidentCandidateContract $a, PlatformIncidentCandidateContract $b): int {
            return strcmp($a->dedupe_fingerprint, $b->dedupe_fingerprint) ?: strcmp($a->incident_key, $b->incident_key);
        });

        $seen = [];
        $out = [];
        foreach ($items as $c) {
            if (isset($seen[$c->dedupe_fingerprint])) {
                continue;
            }
            $seen[$c->dedupe_fingerprint] = true;
            $out[] = $c;
        }

        return $out;
    }
}
