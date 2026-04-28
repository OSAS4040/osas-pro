<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Recommendation;

/**
 * Advisory text only — not executable actions, no ledger/finance directives.
 */
final class RecommendedNextStepPolicy
{
    public function forSignalKey(string $signalKey): string
    {
        return match ($signalKey) {
            'sig.platform.tenant.inactive_cluster',
            'sig.platform.tenant.low_activity_cluster' => 'راقب قائمة الشركات المعروضة خلال 24–48 ساعة، ثم راجع ما إذا كان يجب تجميعها لاحقاً كمرشح حادث — دون أي إجراء تلقائي.',
            'sig.platform.governance.pending_financial_model' => 'راجع طابور النموذج المالي من واجهة الحوكمة المعتادة؛ لا تغيّر بيانات مالية آلياً من هذه الشاشة.',
            'sig.platform.system.queue_failed_pressure' => 'افحص الطابور وfailed_jobs يدوياً وفق إجراءات التشغيل؛ راقب انخفاض العداد بعد الإصلاح.',
            'sig.platform.operations.platform_health_degraded' => 'راجع مؤشرات قاعدة البيانات وRedis والطابور؛ أعد تحميل الملخص بعد التصحيح للتأكد من الاستقرار.',
            'sig.platform.system.scheduler_stamp_missing' => 'تحقق من وصول cron إلى schedule:run؛ إن استمر الغياب فافحص البنية التحتية — مراقبة فقط.',
            'sig.platform.adoption.trial_expiry_window' => 'راجع الشركات ضمن نافذة التجربة قبل انتهائها؛ قرار التحويل يبقى يدوياً وفق سياسة المنصة.',
            'sig.platform.operations.attention_queue_depth' => 'رتّب الأولويات داخل قائمة الانتباه الحالية؛ مهيأ لاحقاً لطبقة مرشحي الحوادث دون فتح حادث الآن.',
            'sig.platform.adoption.signup_pulse' => 'تابع مؤشر التسجيلات أسبوعياً؛ لا حاجة لتدخل ما لم يترافق مع ضغط دعم.',
            default => 'راقب المؤشر ضمن دورة العمل الاعتيادية؛ عند استقرار الأنماط يمكن لاحقاً تقييم تجميعه كمرشح حادث.',
        };
    }
}
