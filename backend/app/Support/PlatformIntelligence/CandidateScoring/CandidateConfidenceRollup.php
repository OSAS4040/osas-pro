<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateScoring;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;

/**
 * Rollup confidence from member signals + structural bonuses/penalties.
 */
final class CandidateConfidenceRollup
{
    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function rollup(array $cluster, int $sharedCorrelationKeyCount): float
    {
        if ($cluster === []) {
            return 0.0;
        }

        $avg = $this->avg($cluster);
        $var = $this->variance($cluster, $avg);
        $supportBonus = min(0.12, 0.03 * (count($cluster) - 1));
        $variancePenalty = $var > 0.04 ? 0.05 : 0.0;
        $overlapBonus = $sharedCorrelationKeyCount >= 2 ? 0.02 : 0.0;
        $scopePenalty = $this->scopeSpread($cluster) >= 4 ? 0.03 : 0.0;

        $v = $avg + $supportBonus - $variancePenalty + $overlapBonus - $scopePenalty;
        $v = max(0.0, min(0.94, $v));

        return round($v, 4);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    public function rationaleAr(array $cluster, float $rolled, int $sharedCorrelationKeyCount): string
    {
        $avg = $this->avg($cluster);
        $var = $this->variance($cluster, $avg);
        $parts = [
            'متوسط ثقة الإشارات: '.round($avg, 3).'.',
            'تشتت الثقة: '.round($var, 4).' (عالي التشتت يخفض الثقة المجمّعة قليلاً).',
            'عدد مفاتيح الارتباط المشتركة بين أعضاء المجموعة: '.$sharedCorrelationKeyCount.'.',
            'الثقة النهائية بعد الدمج: '.round($rolled, 3).' (حد أعلى 0.94 لتجنب ثقة تجميلية).',
        ];

        return implode(' ', $parts);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function avg(array $cluster): float
    {
        $sum = 0.0;
        foreach ($cluster as $s) {
            $sum += $s->confidence;
        }

        return $sum / count($cluster);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function variance(array $cluster, float $mean): float
    {
        if (count($cluster) < 2) {
            return 0.0;
        }
        $acc = 0.0;
        foreach ($cluster as $s) {
            $d = $s->confidence - $mean;
            $acc += $d * $d;
        }

        return $acc / count($cluster);
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function scopeSpread(array $cluster): int
    {
        $scopes = [];
        foreach ($cluster as $s) {
            $scopes[$s->affected_scope] = true;
        }

        return count($scopes);
    }
}
