<?php

declare(strict_types=1);

namespace App\Support\PlatformIntelligence\SignalEngine\Detect;

use App\Support\PlatformIntelligence\Enums\PlatformSignalSourceType;
use App\Support\PlatformIntelligence\Enums\PlatformSignalType;
use App\Support\PlatformIntelligence\SignalEngine\Draft\SignalDraft;
use App\Support\PlatformIntelligence\SignalEngine\Normalize\OverviewSnapshotNormalizer;

final class OverviewBasedSignalDetector
{
    /**
     * @return list<SignalDraft>
     */
    public function detect(OverviewSnapshotNormalizer $norm): array
    {
        $at = $norm->generatedAt();
        $kpis = $norm->kpis();
        $health = $norm->health();
        $alerts = $norm->alerts();
        $attention = $norm->attention();

        $drafts = [];

        $inactive = (int) ($kpis['inactive_companies'] ?? 0);
        if ($inactive > 0) {
            $ids = $this->companyIdsForReasons($attention, ['inactive_14d']);
            $worstDays = $this->worstLastActivityDays($attention, $ids);
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.tenant.inactive_cluster',
                signal_type: PlatformSignalType::MetricThreshold,
                source: PlatformSignalSourceType::Operations,
                title: 'تجمع شركات بدون نشاط تشغيلي ملحوظ (≥14 يوم)',
                summary_stub: 'عدد الشركات في حالة عدم النشاط وفق تعريف المنصة يتجاوز الصفر.',
                why_stub: 'مؤشر النشاط يعتمد على أوامر العمل والفواتير وتسجيلات الدخول خلال 14 يوماً.',
                affected_scope: 'platform:tenants',
                affected_entities: ['tenant_activity_bucket:inactive_14d'],
                affected_company_ids: $ids,
                correlation_keys: [],
                evidence: [
                    'inactive_count' => $inactive,
                    'supporting_factor_count' => min(4, 1 + (int) floor($inactive / 5)),
                    'worst_last_activity_days' => $worstDays,
                    'derived_from_attention' => $ids !== [],
                ],
                source_ref: 'overview:kpis.inactive_companies',
                observed_at: $at,
            );
        }

        $low = (int) ($kpis['low_activity_companies'] ?? 0);
        if ($low > 0) {
            $ids = $this->companyIdsForReasons($attention, ['low_activity_7_14d']);
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.tenant.low_activity_cluster',
                signal_type: PlatformSignalType::Trend,
                source: PlatformSignalSourceType::Operations,
                title: 'شركات منخفضة النشاط (نافذة 7–14 يوماً)',
                summary_stub: 'توجد شركات داخل نطاق النشاط المنخفض حسب تعريف المنصة.',
                why_stub: 'النشاط بين اليوم 8 والـ14 دون حركة في آخر 7 أيام يزيد احتمال التراجع.',
                affected_scope: 'platform:tenants',
                affected_entities: ['tenant_activity_bucket:low_activity_7_14d'],
                affected_company_ids: $ids,
                correlation_keys: [],
                evidence: [
                    'low_activity_count' => $low,
                    'supporting_factor_count' => 2,
                    'derived_from_attention' => $ids !== [],
                ],
                source_ref: 'overview:kpis.low_activity_companies',
                observed_at: $at,
            );
        }

        $pendingFinancial = $this->countAlertType($alerts, 'pending_financial_review');
        if ($pendingFinancial > 0) {
            $ids = $this->companyIdsForReasons($attention, ['pending_financial_review']);
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.governance.pending_financial_model',
                signal_type: PlatformSignalType::Rule,
                source: PlatformSignalSourceType::Governance,
                title: 'مراجعات نموذج مالي معلّقة على مستوى المنصة',
                summary_stub: 'هناك شركات تنتظر قرار النموذج المالي وفق حالة الحساب.',
                why_stub: 'الحالة pending_platform_review تمنع إغلاق حوكمة الاشتراك حتى يتم القرار.',
                affected_scope: 'platform:governance',
                affected_entities: ['governance:financial_model_review'],
                affected_company_ids: $ids,
                correlation_keys: [],
                evidence: [
                    'pending_financial_rows' => $pendingFinancial,
                    'governance_backlog' => true,
                    'supporting_factor_count' => 2,
                    'derived_from_attention' => $ids !== [],
                ],
                source_ref: 'overview:alerts.pending_financial_review',
                observed_at: $at,
            );
        }

        $failedJobs = isset($health['failed_jobs']) && is_numeric($health['failed_jobs']) ? (int) $health['failed_jobs'] : null;
        $queuePressure = $failedJobs !== null && $failedJobs > 20;
        if ($queuePressure || $this->countAlertType($alerts, 'queue_failed_jobs') > 0) {
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.system.queue_failed_pressure',
                signal_type: PlatformSignalType::MetricThreshold,
                source: PlatformSignalSourceType::System,
                title: 'ضغط على مهام الطابور الفاشلة',
                summary_stub: 'عداد failed_jobs تجاوز عتبة التشغيل الآمنة المعتمدة في المحرك.',
                why_stub: 'ارتفاع المهام الفاشلة يرتبط بتأخر المعالجة ويزيد مخاطر تجربة المستأجر.',
                affected_scope: 'platform:runtime',
                affected_entities: ['queue:failed_jobs'],
                affected_company_ids: [],
                correlation_keys: [],
                evidence: [
                    'failed_jobs' => $failedJobs ?? 0,
                    'queue_pressure' => true,
                    'supporting_factor_count' => 2,
                ],
                source_ref: 'overview:health.failed_jobs',
                observed_at: $at,
            );
        }

        $trendDegraded = ($health['trend'] ?? '') === 'degraded' || ($health['api'] ?? '') !== 'ok';
        if ($trendDegraded) {
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.operations.platform_health_degraded',
                signal_type: PlatformSignalType::Anomaly,
                source: PlatformSignalSourceType::Operations,
                title: 'صحة منصة تشغيلية منخفضة',
                summary_stub: 'مؤشرات قاعدة البيانات أو Redis أو الطابور تظهر تدهوراً.',
                why_stub: 'مجموعة فحوصات health في لقطة الملخص التنفيذي أبلغت عن حالة degraded.',
                affected_scope: 'platform:runtime',
                affected_entities: ['health:aggregate'],
                affected_company_ids: [],
                correlation_keys: [],
                evidence: [
                    'health_critical' => true,
                    'supporting_factor_count' => 3,
                ],
                source_ref: 'overview:health.trend',
                observed_at: $at,
            );
        }

        $schedulerStale = empty($health['scheduler_last_run_at'] ?? null);
        if ($schedulerStale) {
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.system.scheduler_stamp_missing',
                signal_type: PlatformSignalType::Rule,
                source: PlatformSignalSourceType::System,
                title: 'لم يُسجّل تشغيل الجدولة مؤخراً',
                summary_stub: 'مفتاح آخر تشغيل للجدولة غير موجود في الكاش التشغيلي.',
                why_stub: 'يُحدَّث المفتاح عند نجاح schedule:run؛ غيابه يعني احتمال عدم وصول cron.',
                affected_scope: 'platform:scheduler',
                affected_entities: ['scheduler:last_run'],
                affected_company_ids: [],
                correlation_keys: [],
                evidence: [
                    'scheduler_stale' => true,
                    'sparse_metrics' => true,
                    'supporting_factor_count' => 1,
                ],
                source_ref: 'overview:health.scheduler_last_run_at',
                observed_at: $at,
            );
        }

        $trialAlerts = count(array_filter($alerts, static fn ($a) => ($a['type'] ?? '') === 'trial_expiring'));
        if ($trialAlerts > 0) {
            $ids = $this->companyIdsForReasons($attention, ['trial_ending']);
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.adoption.trial_expiry_window',
                signal_type: PlatformSignalType::Composite,
                source: PlatformSignalSourceType::Adoption,
                title: 'نوافذ اشتراك تجريبي تقترب من الانتهاء',
                summary_stub: 'هناك تنبيهات تجريبية نشطة من لقطة الانتباه التنفيذية.',
                why_stub: 'التحويل من التجربة إلى مدفوع يتطلب متابعة قبل انتهاء النافذة الزمنية.',
                affected_scope: 'platform:subscriptions',
                affected_entities: ['subscription_status:trial_window'],
                affected_company_ids: $ids,
                correlation_keys: [],
                evidence: [
                    'trial_alert_rows' => $trialAlerts,
                    'supporting_factor_count' => min(3, $trialAlerts),
                    'derived_from_attention' => $ids !== [],
                ],
                source_ref: 'overview:alerts.trial_expiring',
                observed_at: $at,
            );
        }

        $attentionCount = count($attention);
        if ($attentionCount >= 4) {
            $ids = array_values(array_unique(array_map(static fn ($r) => (int) ($r['company_id'] ?? 0), $attention)));
            $ids = array_values(array_filter($ids, static fn ($id) => $id > 0));
            $ids = array_slice($ids, 0, 25);
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.operations.attention_queue_depth',
                signal_type: PlatformSignalType::Correlation,
                source: PlatformSignalSourceType::Operations,
                title: 'عمق قائمة انتباه المشغّل مرتفع',
                summary_stub: 'عدد صفوف الشركات التي تحتاج متابعة يتجاوز العتبة التشغيلية المعتمدة.',
                why_stub: 'ازدحام القائمة يزيد احتمال تفويت أولويات ما لم تُجمع لاحقاً في طبقة المرشحين.',
                affected_scope: 'platform:operations',
                affected_entities: ['attention:queue_depth'],
                affected_company_ids: $ids,
                correlation_keys: [],
                evidence: [
                    'attention_row_count' => $attentionCount,
                    'supporting_factor_count' => 2,
                    'derived_from_attention' => true,
                ],
                source_ref: 'overview:companies_requiring_attention',
                observed_at: $at,
            );
        }

        $new7 = (int) ($kpis['companies_new_7d'] ?? 0);
        $new30 = (int) ($kpis['companies_new_30d'] ?? 0);
        if ($new7 > 0 && $new30 > 0 && ($new7 / max(1, $new30)) >= 0.25) {
            $drafts[] = new SignalDraft(
                draft_key: 'sig.platform.adoption.signup_pulse',
                signal_type: PlatformSignalType::Trend,
                source: PlatformSignalSourceType::Adoption,
                title: 'نبض تسجيل شركات إيجابي في نافذة 7 أيام',
                summary_stub: 'حصة التسجيلات الجديدة خلال 7 أيام مرتفعة نسبةً إلى 30 يوماً.',
                why_stub: 'يستند الإشارة إلى مؤشرات companies_new_7d و companies_new_30d في الملخص التنفيذي.',
                affected_scope: 'platform:growth',
                affected_entities: ['growth:signups_ratio'],
                affected_company_ids: [],
                correlation_keys: [],
                evidence: [
                    'companies_new_7d' => $new7,
                    'companies_new_30d' => $new30,
                    'supporting_factor_count' => 2,
                ],
                source_ref: 'overview:kpis.companies_new_7d',
                observed_at: $at,
            );
        }

        return $drafts;
    }

    /**
     * @param  list<array<string, mixed>>  $attention
     * @param  list<string>  $reasons
     * @return list<int>
     */
    private function companyIdsForReasons(array $attention, array $reasons): array
    {
        $ids = [];
        foreach ($attention as $row) {
            $reason = (string) ($row['reason'] ?? '');
            $reasonsList = $row['reasons'] ?? [];
            $match = in_array($reason, $reasons, true);
            if (! $match && is_array($reasonsList)) {
                foreach ($reasons as $r) {
                    if (in_array($r, $reasonsList, true)) {
                        $match = true;
                        break;
                    }
                }
            }
            if (! $match) {
                continue;
            }
            $id = (int) ($row['company_id'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return array_slice($ids, 0, 25);
    }

    /**
     * @param  list<array<string, mixed>>  $attention
     * @param  list<int>  $ids
     */
    private function worstLastActivityDays(array $attention, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }
        $set = array_fill_keys($ids, true);
        $worst = 0;
        foreach ($attention as $row) {
            $id = (int) ($row['company_id'] ?? 0);
            if (! isset($set[$id])) {
                continue;
            }
            $d = (int) ($row['last_activity_days_ago'] ?? 0);
            $worst = max($worst, $d);
        }

        return $worst;
    }

    /**
     * @param  list<array<string, mixed>>  $alerts
     */
    private function countAlertType(array $alerts, string $type): int
    {
        $n = 0;
        foreach ($alerts as $a) {
            if (($a['type'] ?? '') === $type) {
                $n++;
            }
        }

        return $n;
    }
}
