<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\CandidateEngine;

use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Guidance-only strings — no execution, no ledger/finance wording.
 */
final class IncidentCandidateRecommendedActionsPolicy
{
    /**
     * @return list<string>
     */
    public function forCandidate(
        PlatformIntelligenceSeverity $severity,
        float $confidence,
        int $signalCount,
        int $companyCount,
    ): array {
        $out = ['مراجعة تشغيلية مقترحة (قراءة فقط في هذه المرحلة).'];

        if ($severity === PlatformIntelligenceSeverity::Info || $confidence < 0.55) {
            $out[] = 'راقب خلال نافذة زمنية قصيرة عبر تحديثات اللقطة التالية قبل أي قرار تشغيلي لاحق.';
        }
        if ($signalCount >= 3) {
            $out[] = 'مرشّح مناسب للتحويل إلى حادث مُدار لاحقًا عند استمرار النمط عبر تحديثات متتالية.';
        }
        if ($companyCount >= 2) {
            $out[] = 'راجع قائمة الشركات المتأثرة في الحقول affected_companies.';
        }
        if ($severity === PlatformIntelligenceSeverity::High || $severity === PlatformIntelligenceSeverity::Critical) {
            $out[] = 'أولوية مراجعة أعلى بسبب الشدة المجمّعة؛ لا يزال دون أي إجراء تنفيذي من النظام.';
        }

        return array_values(array_unique($out));
    }
}
