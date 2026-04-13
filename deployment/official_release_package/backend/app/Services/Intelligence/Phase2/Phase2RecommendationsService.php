<?php

namespace App\Services\Intelligence\Phase2;

use Illuminate\Http\Request;

/**
 * Rule-based, read-only suggestions derived from domain_events aggregates.
 * Not financial advice; no automation or writes.
 */
final class Phase2RecommendationsService
{
    public function __construct(
        private readonly Phase2InsightsService $insights,
    ) {}

    /**
     * @return list<array{id: string, severity: string, title: string, detail: string, basis: string}>
     */
    public function build(Request $request): array
    {
        $insights = $this->insights->build($request);
        $total = (int) ($insights['totals']['events'] ?? 0);
        $byName = $insights['by_event_name'] ?? [];

        $out = [];

        if ($total === 0) {
            $out[] = [
                'id'       => 'no_events_in_window',
                'severity' => 'info',
                'title'    => 'لا توجد أحداث مجال في النافذة المحددة',
                'detail'   => 'إذا كنت تتوقع بيانات تتبّع، تحقق من تفعيل طبقة الأحداث (INTELLIGENT_EVENTS_ENABLED وINTELLIGENT_EVENTS_PERSIST_ENABLED) وأن العمليات تمر بالمسارات المسجّلة.',
                'basis'    => 'عدد أحداث المجال في النافذة = 0',
            ];

            return $out;
        }

        $countsByName = [];
        foreach ($byName as $row) {
            $countsByName[$row['event_name']] = (int) $row['count'];
        }

        if ($total > 0 && isset($countsByName['CustomerCreated'])) {
            $cc = $countsByName['CustomerCreated'];
            if ($cc / $total >= 0.75) {
                $out[] = [
                    'id'       => 'customer_created_dominance',
                    'severity' => 'info',
                    'title'    => 'أحداث «إنشاء عميل» تهيمن على المزيج',
                    'detail'   => 'معظم الأحداث المسجّلة من نوع إنشاء عميل. وسّع نافذة الزمن أو فعّل التسجيل في مسارات أخرى إن احتجت تغطية أوسع.',
                    'basis'    => 'نسبة CustomerCreated ≥ 75٪ من أحداث النافذة',
                ];
            }
        }

        $debits = (int) ($countsByName['WalletDebited'] ?? 0);
        $credits = (int) ($countsByName['WalletCredited'] ?? 0);
        if ($debits > 0 && $credits > 0 && $debits > $credits * 5) {
            $out[] = [
                'id'       => 'wallet_debit_credit_skew',
                'severity' => 'info',
                'title'    => 'أحداث خصم المحفظة أكثر بكثير من أحداث الإيداع',
                'detail'   => 'نسبة أحداث المجال تُظهر خصومات أكثر من إيداعات في هذه النافذة. راجع إن كان ذلك متوافقاً مع النشاط المتوقع (مراقبة فقط، ليس حكماً مالياً).',
                'basis'    => 'WalletDebited > 5 × WalletCredited في النافذة',
            ];
        }

        $top = $byName[0] ?? null;
        if ($top && $total > 10) {
            $share = ((int) $top['count']) / $total;
            if ($share >= 0.8) {
                $out[] = [
                    'id'       => 'single_event_concentration',
                    'severity' => 'warning',
                    'title'    => 'تركيز شديد على نوع حدث واحد',
                    'detail'   => 'نوع حدث واحد يمثل معظم الحركة. تأكد أن المسارات المهمة الأخرى تُصدِر أحداث مجال عند تفعيل الحفظ.',
                    'basis'    => 'نصيب أعلى نوع ≥ 80٪ مع إجمالي أحداث > 10',
                ];
            }
        }

        if ($out === []) {
            $out[] = [
                'id'       => 'no_recommendations',
                'severity' => 'info',
                'title'    => 'لا أنماط بارزة في النافذة الحالية',
                'detail'   => 'القواعد الاستدلالية لم ترصد انحرافاً أو قفزات أو فجوات خارج التباين الطبيعي.',
                'basis'    => 'محرك القواعد — لم تُفعَّل أي عتبة',
            ];
        }

        return $out;
    }
}
