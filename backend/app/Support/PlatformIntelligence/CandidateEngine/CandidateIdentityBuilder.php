<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Deterministic incident_key / dedupe_fingerprint / incident_type for candidates.
 */
final class CandidateIdentityBuilder
{
    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function incidentKey(array $cluster): string
    {
        $payload = PlatformIncidentCandidateRulesVersion::INCIDENT_KEY_SALT
            ."\n".implode("\n", $this->sortedSignalKeys($cluster))
            ."\n".implode("\n", $this->sortedAllCorrelationKeys($cluster))
            ."\n".$this->scopeAnchor($cluster)
            ."\n".$this->signalTypeAnchor($cluster)
            ."\n".$this->sourceAnchor($cluster);

        return 'icand_'.hash('sha256', $payload);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function dedupeFingerprint(PlatformIntelligenceSeverity $severity, array $cluster): string
    {
        $payload = implode(',', $this->sortedSignalKeys($cluster))
            .'|'.$this->scopeAnchor($cluster)
            .'|'.$this->dominantSource($cluster)
            .'|'.$severity->value
            .'|'.(string) $this->uniqueCompanyCount($cluster);

        return hash('sha256', $payload);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function incidentType(array $cluster, bool $hasSharedCorrelationIntersection): string
    {
        if ($hasSharedCorrelationIntersection) {
            return 'candidate.shared_correlation';
        }
        if (count($cluster) > 1) {
            return 'candidate.multi_signal_overlap';
        }

        return 'candidate.single_signal';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @return list<string>
     */
    public function sortedSignalKeys(array $cluster): array
    {
        $keys = array_map(static fn (PlatformSignalContract $s) => $s->signal_key, $cluster);
        sort($keys, SORT_STRING);

        return array_values($keys);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function sortedAllCorrelationKeys(array $cluster): array
    {
        $set = [];
        foreach ($cluster as $s) {
            foreach ($s->correlation_keys as $k) {
                $t = trim((string) $k);
                if ($t !== '') {
                    $set[$t] = true;
                }
            }
        }
        $keys = array_keys($set);
        sort($keys, SORT_STRING);

        return $keys;
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function scopeAnchor(array $cluster): string
    {
        $scopes = [];
        foreach ($cluster as $s) {
            $scopes[$s->affected_scope] = true;
        }
        $list = array_keys($scopes);
        sort($list, SORT_STRING);

        return $list[0] ?? 'unknown_scope';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function signalTypeAnchor(array $cluster): string
    {
        $types = [];
        foreach ($cluster as $s) {
            $types[$s->signal_type->value] = true;
        }
        $list = array_keys($types);
        sort($list, SORT_STRING);

        return $list[0] ?? 'unknown_type';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function sourceAnchor(array $cluster): string
    {
        $src = [];
        foreach ($cluster as $s) {
            $src[$s->source->value] = true;
        }
        $list = array_keys($src);
        sort($list, SORT_STRING);

        return $list[0] ?? 'unknown_source';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function dominantSource(array $cluster): string
    {
        $counts = [];
        foreach ($cluster as $s) {
            $v = $s->source->value;
            $counts[$v] = ($counts[$v] ?? 0) + 1;
        }
        arsort($counts);
        foreach ($counts as $k => $_) {
            return (string) $k;
        }

        return 'unknown_source';
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function uniqueCompanyCount(array $cluster): int
    {
        $set = [];
        foreach ($cluster as $s) {
            foreach ($s->affected_companies as $id) {
                $set[(string) $id] = true;
            }
        }

        return count($set);
    }
}
