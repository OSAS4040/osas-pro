<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Explainability;

use App\Support\PlatformIntelligence\Contracts\PlatformSignalContract;
use App\Support\PlatformIntelligence\Enums\PlatformIntelligenceSeverity;

/**
 * Produces operator-grade why/summary text (no marketing fluff).
 */
final class SignalExplainabilityComposer
{
    public function compose(PlatformSignalContract $signal): PlatformSignalContract
    {
        $severityLine = $this->severityExplainAr($signal->severity);
        $confidenceLine = $this->confidenceExplainAr($signal->confidence);

        $why = trim($signal->why_summary."\n— ".$severityLine."\n— ".$confidenceLine);

        $summary = $signal->summary;
        if (! str_contains($summary, 'الشركات المتأثرة')) {
            $n = count($signal->affected_companies);
            if ($n > 0) {
                $summary .= ' — الشركات المتأثرة: '.$n.' (معرّفات في الحقل affected_companies).';
            }
        }

        return new PlatformSignalContract(
            signal_key: $signal->signal_key,
            signal_type: $signal->signal_type,
            title: $signal->title,
            summary: $summary,
            why_summary: $why,
            severity: $signal->severity,
            confidence: $signal->confidence,
            source: $signal->source,
            source_ref: $signal->source_ref,
            affected_scope: $signal->affected_scope,
            affected_entities: $signal->affected_entities,
            affected_companies: $signal->affected_companies,
            first_seen_at: $signal->first_seen_at,
            last_seen_at: $signal->last_seen_at,
            recommended_next_step: $signal->recommended_next_step,
            correlation_keys: $signal->correlation_keys,
            trace_id: $signal->trace_id,
            correlation_id: $signal->correlation_id,
        );
    }

    private function severityExplainAr(PlatformIntelligenceSeverity $s): string
    {
        return match ($s) {
            PlatformIntelligenceSeverity::Info => 'تفسير الشدة: معلومات تشغيلية — لا يتطلب تدخلاً فورياً.',
            PlatformIntelligenceSeverity::Low => 'تفسير الشدة: منخفضة — راقب ضمن دورة العمل الاعتيادية.',
            PlatformIntelligenceSeverity::Medium => 'تفسير الشدة: متوسطة — تستحق مراجعة مجدولة ضمن نافذة عمل قصيرة.',
            PlatformIntelligenceSeverity::High => 'تفسير الشدة: مرتفعة — راجع الأولويات التشغيلية قبل توسع الأثر.',
            PlatformIntelligenceSeverity::Critical => 'تفسير الشدة: حرجة — يتطلب اهتماماً فورياً من مشغّل المنصة.',
        };
    }

    private function confidenceExplainAr(float $confidence): string
    {
        if ($confidence >= 0.78) {
            return 'تفسير الثقة: مرتفعة — مدخلات الملخص التنفيذي مكتملة والعوامل الداعمة متسقة.';
        }
        if ($confidence >= 0.55) {
            return 'تفسير الثقة: متوسطة — البيانات كافية لكن قد ت缺失 تفاصيل تشغيلية دقيقة لبعض الشركات.';
        }

        return 'تفسير الثقة: منخفضة — اعتمد على المؤشر كاستكشاف ثم ثبّت بالتحقق اليدوي قبل أي خطوة لاحقة.';
    }
}
