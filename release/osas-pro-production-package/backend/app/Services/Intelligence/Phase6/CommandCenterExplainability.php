<?php

namespace App\Services\Intelligence\Phase6;

/**
 * Phase 6 — Read-only explainability for command-center items (Why Engine).
 * Uses Phase 2 alert/recommendation payloads + insights aggregates only.
 */
final class CommandCenterExplainability
{
    /**
     * @param  array<string, mixed>  $insights  Phase2InsightsService::build output
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    public static function forAlert(array $a, array $insights): array
    {
        return match ($a['id'] ?? '') {
            'event_volume_spike' => self::volumeSpikeAlert($a),
            'event_record_failures_present' => self::ingestionFailuresAlert($a),
            'zero_events_while_persist_enabled' => self::zeroEventsAlert($a, $insights),
            default => self::genericAlert($a),
        };
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    public static function forRecommendation(array $r, array $insights): array
    {
        return match ($r['id'] ?? '') {
            'no_events_in_window' => self::noEventsRec($insights),
            'customer_created_dominance' => self::customerDominanceRec($insights),
            'wallet_debit_credit_skew' => self::walletSkewRec($insights),
            'single_event_concentration' => self::concentrationRec($insights),
            'no_recommendations' => self::noRulesRec(),
            default => self::genericRecommendation($r),
        };
    }

    /**
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function volumeSpikeAlert(array $a): array
    {
        $basis = (string) ($a['basis'] ?? '');
        $last = null;
        $prev = null;
        if (preg_match('/last_24h=(\d+).*prior_24h=(\d+)/s', $basis, $m)) {
            $last = (int) $m[1];
            $prev = (int) $m[2];
        }

        $why = [
            'نقارن عدد أحداث المجال المسجّلة في آخر 24 ساعة مع الـ 24 ساعة التي قبلها.',
            'لا يُعتبر هذا تنبيهًا ماليًا — مراقبة تشغيلية فقط.',
        ];
        if ($last !== null && $prev !== null) {
            array_unshift($why, "العدد الأخير: {$last}، العدد السابق: {$prev}.");
        }

        return [
            'why_details' => $why,
            'signals_used' => [
                'domain_events.count_24h_rolling_last',
                'domain_events.count_24h_rolling_prior',
            ],
            'thresholds' => [
                'prior_24h_minimum' => 5,
                'last_must_exceed_prior_by_factor' => 2,
                'rule_id' => 'event_volume_spike',
            ],
            'confidence' => 0.9,
        ];
    }

    /**
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function ingestionFailuresAlert(array $a): array
    {
        $count = null;
        if (preg_match('/\)\s*=\s*(\d+)/', (string) ($a['basis'] ?? ''), $m)) {
            $count = (int) $m[1];
        }

        $why = [
            'يُفحص جدول فشل تسجيل الأحداث (قراءة فقط) للنطاق الحالي.',
            'أي صف يعني أن حدثًا لم يُخزَّن كما هو متوقع.',
        ];
        if ($count !== null) {
            array_unshift($why, "عدد السجلات الأخيرة في نافذة المراقبة: {$count}.");
        }

        return [
            'why_details' => $why,
            'signals_used' => [
                'event_record_failures.count_recent',
            ],
            'thresholds' => [
                'minimum_rows_to_alert' => 1,
                'lookback_days' => 7,
                'rule_id' => 'event_record_failures_present',
            ],
            'confidence' => $count !== null && $count > 0 ? 0.92 : 0.75,
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function zeroEventsAlert(array $a, array $insights): array
    {
        $total = (int) ($insights['totals']['events'] ?? 0);
        $persist = (bool) config('intelligent.events.persist.enabled');

        return [
            'why_details' => [
                'في نافذة الزمن المحددة لا توجد أحداث مجال مسجّلة، بينما تسجيل الأحداث مفعّل في الإعدادات.',
                'قد يعني ذلك عدم وصول حركة إلى المسارات المجهّزة، أو نافذة زمن ضيقة.',
                'الغرض: التحقق من التغطية — وليس اتهامًا بالخطأ المالي.',
            ],
            'signals_used' => [
                'domain_events.count_in_requested_window',
                'config.intelligent.events.persist.enabled',
            ],
            'thresholds' => [
                'events_in_window_must_be' => 0,
                'persist_must_be' => true,
                'observed_events_in_window' => $total,
                'persist_enabled' => $persist,
                'rule_id' => 'zero_events_while_persist_enabled',
            ],
            'confidence' => 0.88,
        ];
    }

    /**
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function genericAlert(array $a): array
    {
        return [
            'why_details' => [
                'يستند التنبيه إلى قاعدة قراءة فقط على بيانات المراقبة المتاحة.',
                'راجع الحقل «الأساس» في واجهة الرؤى للتفاصيل الخام عند الحاجة.',
            ],
            'signals_used' => [
                'domain_events.scoped_query',
            ],
            'thresholds' => [
                'rule_id' => $a['id'] ?? 'unknown',
            ],
            'confidence' => 0.65,
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function noEventsRec(array $insights): array
    {
        $total = (int) ($insights['totals']['events'] ?? 0);

        return [
            'why_details' => [
                'لا توجد أحداث مجال في نافذة الزمن الحالية — لذلك تظهر هذه الرسالة بدل تحليل الأنماط.',
                'تحقق من تفعيل طبقة الأحداث (Phase 1) ومن أن العمليات تمر بالمسارات المسجّلة.',
            ],
            'signals_used' => [
                'domain_events.total_in_window',
            ],
            'thresholds' => [
                'minimum_events_for_pattern_rules' => 1,
                'observed' => $total,
                'rule_id' => 'no_events_in_window',
            ],
            'confidence' => 0.95,
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function customerDominanceRec(array $insights): array
    {
        $byName = $insights['by_event_name'] ?? [];
        $total = (int) ($insights['totals']['events'] ?? 0);
        $cc = 0;
        foreach ($byName as $row) {
            if (($row['event_name'] ?? '') === 'CustomerCreated') {
                $cc = (int) ($row['count'] ?? 0);
                break;
            }
        }
        $share = $total > 0 ? round($cc / $total, 3) : 0.0;

        return [
            'why_details' => [
                'نحسب نسبة أحداث «إنشاء عميل» من إجمالي أحداث المجال في النافذة.',
                'إذا تجاوزت النسبة 75٪ يُعتبر المزيج غير متوازن لأغراض المراقبة.',
            ],
            'signals_used' => [
                'domain_events.by_event_name.CustomerCreated',
                'domain_events.totals.events',
            ],
            'thresholds' => [
                'customer_created_share_minimum' => 0.75,
                'observed_share' => $share,
                'observed_customer_created' => $cc,
                'observed_total_events' => $total,
                'rule_id' => 'customer_created_dominance',
            ],
            'confidence' => 0.82,
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function walletSkewRec(array $insights): array
    {
        $byName = [];
        foreach ($insights['by_event_name'] ?? [] as $row) {
            $byName[$row['event_name'] ?? ''] = (int) ($row['count'] ?? 0);
        }
        $debits = (int) ($byName['WalletDebited'] ?? 0);
        $credits = (int) ($byName['WalletCredited'] ?? 0);

        return [
            'why_details' => [
                'نقارن عدد أحداث الخصم والإيداع للمحفظة في النافذة.',
                'يُفعّل التحذير عندما يكون الخصم أكبر من خمسة أضعاف الإيداع (مراقبة فقط).',
            ],
            'signals_used' => [
                'domain_events.by_event_name.WalletDebited',
                'domain_events.by_event_name.WalletCredited',
            ],
            'thresholds' => [
                'debits_must_exceed_credits_by_factor' => 5,
                'credits_must_be' => '> 0',
                'observed_debits' => $debits,
                'observed_credits' => $credits,
                'rule_id' => 'wallet_debit_credit_skew',
            ],
            'confidence' => 0.8,
        ];
    }

    /**
     * @param  array<string, mixed>  $insights
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function concentrationRec(array $insights): array
    {
        $total = (int) ($insights['totals']['events'] ?? 0);
        $top = $insights['by_event_name'][0] ?? null;
        $topName = $top['event_name'] ?? '';
        $topCount = (int) ($top['count'] ?? 0);
        $share = $total > 0 ? round($topCount / $total, 3) : 0.0;

        return [
            'why_details' => [
                'نحدد أكثر نوع حدث تكرارًا في النافذة ونحسب نسبته من الإجمالي.',
                'إذا كانت النسبة ≥ 80٪ والعدد الكلي > 10 فهناك تركيز عالٍ على نوع واحد.',
            ],
            'signals_used' => [
                'domain_events.top_event_name_share',
                'domain_events.totals.events',
            ],
            'thresholds' => [
                'top_event_share_minimum' => 0.8,
                'total_events_minimum' => 10,
                'observed_top_event' => $topName,
                'observed_share' => $share,
                'observed_total_events' => $total,
                'rule_id' => 'single_event_concentration',
            ],
            'confidence' => 0.84,
        ];
    }

    /**
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function noRulesRec(): array
    {
        return [
            'why_details' => [
                'المحرّك راجع نفس مجاميع الأحداث ولم يجد انحرافًا يتجاوز العتبات المعرّفة.',
                'هذا يعني «لا تنبيه أنماط» وليس بالضرورة أن النظام خالٍ من العمل.',
            ],
            'signals_used' => [
                'domain_events.totals',
                'domain_events.by_event_name',
                'recommendations.rule_engine_v1',
            ],
            'thresholds' => [
                'any_pattern_rule_triggered' => false,
                'rule_id' => 'no_recommendations',
            ],
            'confidence' => 0.7,
        ];
    }

    /**
     * @return array{why_details: list<string>, signals_used: list<string>, thresholds: array<string, mixed>, confidence: float|null}
     */
    private static function genericRecommendation(array $r): array
    {
        return [
            'why_details' => [
                'توصية مبنية على تجميعات أحداث المجال في نافذة الزمن الحالية.',
            ],
            'signals_used' => [
                'domain_events.aggregates',
            ],
            'thresholds' => [
                'rule_id' => $r['id'] ?? 'unknown',
            ],
            'confidence' => 0.68,
        ];
    }
}
