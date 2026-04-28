<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateCorrelation;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;

/**
 * Groups eligible signals into correlation clusters (Union-Find).
 *
 * Edges (documented):
 * 1) Non-empty intersection of normalized correlation_keys.
 * 2) Same affected_scope AND same signal_type AND ≥1 shared affected company id.
 * 3) Same source domain AND ≥2 shared affected company ids.
 */
final class CandidateGroupingService
{
    /**
     * @param  list<PlatformSignalContract>  $eligible
     * @return list<list<PlatformSignalContract>>
     */
    public function partition(array $eligible): array
    {
        $n = count($eligible);
        if ($n === 0) {
            return [];
        }

        $uf = new CandidateUnionFind($n);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->mergeable($eligible[$i], $eligible[$j])) {
                    $uf->union($i, $j);
                }
            }
        }

        /** @var array<int, list<int>> $buckets */
        $buckets = [];
        for ($i = 0; $i < $n; $i++) {
            $r = $uf->find($i);
            $buckets[$r][] = $i;
        }

        $clusters = [];
        foreach ($buckets as $indices) {
            $cluster = [];
            foreach ($indices as $idx) {
                $cluster[] = $eligible[$idx];
            }
            usort($cluster, static fn (PlatformSignalContract $a, PlatformSignalContract $b) => strcmp($a->signal_key, $b->signal_key));
            $clusters[] = $cluster;
        }

        usort($clusters, static function (array $x, array $y): int {
            $kx = $x[0]->signal_key;
            $ky = $y[0]->signal_key;

            return strcmp($kx, $ky);
        });

        return $clusters;
    }

    private function mergeable(PlatformSignalContract $a, PlatformSignalContract $b): bool
    {
        if ($this->correlationOverlap($a, $b)) {
            return true;
        }

        if ($a->affected_scope === $b->affected_scope
            && $a->signal_type === $b->signal_type
            && $this->companyOverlapCount($a, $b) >= 1) {
            return true;
        }

        if ($a->source === $b->source && $this->companyOverlapCount($a, $b) >= 2) {
            return true;
        }

        return false;
    }

    private function correlationOverlap(PlatformSignalContract $a, PlatformSignalContract $b): bool
    {
        $ak = $this->normalizedCorrelationKeySet($a);
        $bk = $this->normalizedCorrelationKeySet($b);

        return count(array_intersect_key($ak, $bk)) > 0;
    }

    /**
     * @return array<string, true>
     */
    private function normalizedCorrelationKeySet(PlatformSignalContract $s): array
    {
        $out = [];
        foreach ($s->correlation_keys as $k) {
            $t = trim((string) $k);
            if ($t !== '') {
                $out[$t] = true;
            }
        }

        return $out;
    }

    private function companyOverlapCount(PlatformSignalContract $a, PlatformSignalContract $b): int
    {
        $as = $this->companyIdSet($a);
        $bs = $this->companyIdSet($b);

        return count(array_intersect_key($as, $bs));
    }

    /**
     * @return array<string, true>
     */
    private function companyIdSet(PlatformSignalContract $s): array
    {
        $out = [];
        foreach ($s->affected_companies as $id) {
            $out[(string) $id] = true;
        }

        return $out;
    }
}
