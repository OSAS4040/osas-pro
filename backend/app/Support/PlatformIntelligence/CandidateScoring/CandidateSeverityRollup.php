<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateScoring;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Rollup severity for a candidate cluster from member signals (bounded bumps).
 *
 * @see docs/Platform_Intelligence_Incident_Candidate_Layer.md
 */
final class CandidateSeverityRollup
{
    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function rollup(array $cluster): PlatformIntelligenceSeverity
    {
        $base = $this->maxSeverity($cluster);
        $r = $this->rank($base);
        $n = count($cluster);
        $uc = $this->uniqueCompanyCount($cluster);

        if ($r < 4 && $n >= 6) {
            $r++;
        }
        if ($r < 4 && $uc >= 12) {
            $r++;
        }
        if ($r === 0 && $n >= 3 && $this->avgConfidence($cluster) >= 0.55) {
            $r = 1;
        }

        return $this->fromRank(min(4, $r));
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function rationaleAr(PlatformIntelligenceSeverity $rolled, array $cluster): string
    {
        $maxS = $this->maxSeverity($cluster);
        $n = count($cluster);
        $uc = $this->uniqueCompanyCount($cluster);

        $parts = [
            'الحد الأقصى لشدة الإشارات المدخلة: '.$maxS->value.'.',
            'عدد الإشارات في المجموعة: '.$n.'.',
            'عدد الشركات المتأثرة (فريد): '.$uc.'.',
        ];
        if ($rolled->value !== $maxS->value) {
            $parts[] = 'تم ضبط الشدة النهائية وفق قواعد التجميع (حد أعلى للرفع تدريجيًا دون رفع كل شيء إلى حرج).';
        }

        return implode(' ', $parts);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function maxSeverity(array $cluster): PlatformIntelligenceSeverity
    {
        $best = PlatformIntelligenceSeverity::Info;
        $br = 0;
        foreach ($cluster as $s) {
            $r = $this->rank($s->severity);
            if ($r > $br) {
                $br = $r;
                $best = $s->severity;
            }
        }

        return $best;
    }

    private function rank(PlatformIntelligenceSeverity $s): int
    {
        return match ($s) {
            PlatformIntelligenceSeverity::Info => 0,
            PlatformIntelligenceSeverity::Low => 1,
            PlatformIntelligenceSeverity::Medium => 2,
            PlatformIntelligenceSeverity::High => 3,
            PlatformIntelligenceSeverity::Critical => 4,
        };
    }

    private function fromRank(int $r): PlatformIntelligenceSeverity
    {
        return match ($r) {
            0 => PlatformIntelligenceSeverity::Info,
            1 => PlatformIntelligenceSeverity::Low,
            2 => PlatformIntelligenceSeverity::Medium,
            3 => PlatformIntelligenceSeverity::High,
            default => PlatformIntelligenceSeverity::Critical,
        };
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

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function avgConfidence(array $cluster): float
    {
        if ($cluster === []) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($cluster as $s) {
            $sum += $s->confidence;
        }

        return $sum / count($cluster);
    }
}
