<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateExplainability;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Operator-facing Arabic explainability for incident candidates (no lifecycle language).
 */
final class IncidentCandidateExplainabilityComposer
{
    /**
     * @param  list<PlatformSignalContract>  $cluster
     * @return array{grouping_reason: string, why_summary: string, summary: string, title: string}
     */
    public function compose(
        array $cluster,
        PlatformIntelligenceSeverity $rollupSeverity,
        float $rollupConfidence,
        bool $hasSharedCorrelationIntersection,
        string $severityRationaleAr,
        string $confidenceRationaleAr,
    ): array {
        $n = count($cluster);
        $keys = array_map(static fn (PlatformSignalContract $s) => $s->signal_key, $cluster);
        sort($keys, SORT_STRING);

        $grouping = $this->groupingReason($cluster, $hasSharedCorrelationIntersection, $n);

        $signalLines = [];
        foreach ($cluster as $s) {
            $signalLines[] = '- '.$s->signal_key.' ('.$s->signal_type->value.', '.$s->severity->value.', ثقة '.round($s->confidence, 2).')';
        }

        $summary = 'الإشارات المساهمة ('.implode(', ', $keys)."):\n".implode("\n", $signalLines);

        $why = "لماذا مرشّح واحد: {$grouping}\n";
        $why .= "لماذا هذه الشدة: {$severityRationaleAr}\n";
        $why .= "لماذا هذه الثقة: {$confidenceRationaleAr}\n";
        $why .= 'لماذا لم تُترك منفصلة: '.($n > 1
            ? 'لوجود ارتباط تشغيلي موثّق (مفاتيح ارتباط أو تقاطع نطاق/نوع/شركات وفق قواعد التجميع).'
            : 'مجموعة مكونة من إشارة واحدة مؤهّلة بحد ذاتها بعد تجاوز شروط الأهلية.');

        $sortedForTitle = [...$cluster];
        usort($sortedForTitle, fn (PlatformSignalContract $a, PlatformSignalContract $b): int => $this->sortClusterForTitle($a, $b));
        $titleLead = $sortedForTitle[0];
        $title = $n > 1
            ? $titleLead->title.' — تجميع مرشّح ('.$n.' إشارات)'
            : $titleLead->title.' — مرشّح تشغيلي';

        return [
            'grouping_reason' => $grouping,
            'why_summary' => trim($why),
            'summary' => $summary,
            'title' => $title,
        ];
    }

    /**
     * @param  list<PlatformSignalContract>  $cluster
     */
    private function groupingReason(array $cluster, bool $hasSharedCorrelationIntersection, int $n): string
    {
        if ($n === 1) {
            return 'إشارة واحدة مؤهّلة؛ لا يوجد تجميع مع إشارات أخرى ضمن قواعد الارتباط الحالية.';
        }
        if ($hasSharedCorrelationIntersection) {
            return 'تُجمع هذه الإشارات لأنها تشترك في مفتاح ارتباط واحد على الأقل عبر جميع أعضاء المجموعة (تقاطع مفاتيح الارتباط غير فارغ).';
        }

        return 'تُجمع هذه الإشارات لوجود تقاطع تشغيلي: نفس النطاق ونوع الإشارة مع شركات متأثرة مشتركة، أو نفس نطاق المصدر مع تقاطع شركات ≥2، وفق قواعد التجميع المعتمدة.';
    }

    private function sortClusterForTitle(PlatformSignalContract $a, PlatformSignalContract $b): int
    {
        $ra = $this->sevRank($a->severity);
        $rb = $this->sevRank($b->severity);
        if ($ra !== $rb) {
            return $rb <=> $ra;
        }
        if ($a->confidence !== $b->confidence) {
            return $b->confidence <=> $a->confidence;
        }

        return strcmp($a->signal_key, $b->signal_key);
    }

    private function sevRank(PlatformIntelligenceSeverity $s): int
    {
        return match ($s) {
            PlatformIntelligenceSeverity::Info => 0,
            PlatformIntelligenceSeverity::Low => 1,
            PlatformIntelligenceSeverity::Medium => 2,
            PlatformIntelligenceSeverity::High => 3,
            PlatformIntelligenceSeverity::Critical => 4,
        };
    }
}
