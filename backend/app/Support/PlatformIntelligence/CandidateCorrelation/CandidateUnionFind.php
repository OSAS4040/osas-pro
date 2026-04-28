<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateCorrelation;

/**
 * Disjoint-set for deterministic candidate clustering (order-independent unions).
 */
final class CandidateUnionFind
{
    /** @var list<int> */
    private array $parent;

    public function __construct(int $n)
    {
        $this->parent = range(0, max(0, $n - 1));
    }

    public function find(int $i): int
    {
        $p = &$this->parent[$i];
        if ($p !== $i) {
            $p = $this->find($p);
        }

        return $p;
    }

    public function union(int $a, int $b): void
    {
        $ra = $this->find($a);
        $rb = $this->find($b);
        if ($ra === $rb) {
            return;
        }
        if ($ra < $rb) {
            $this->parent[$rb] = $ra;
        } else {
            $this->parent[$ra] = $rb;
        }
    }
}
