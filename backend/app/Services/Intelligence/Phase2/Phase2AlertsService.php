<?php

namespace App\Services\Intelligence\Phase2;

use App\Models\EventRecordFailure;
use Illuminate\Http\Request;

/**
 * Threshold-style notices from read-only counts (no notifications, no jobs).
 */
final class Phase2AlertsService
{
    public function __construct(
        private readonly Phase2DomainEventQuery $queries,
    ) {}

    /**
     * @return list<array{id: string, severity: string, type: string, message: string, detected_at: string, basis: string}>
     */
    public function build(Request $request): array
    {
        $alerts = [];
        $now = now();

        [$from, $to] = $this->queries->resolveWindow($request);
        $base = $this->queries->scopedBuilder($request);

        $lastStart = $now->copy()->subDay();
        $lastEnd = $now;
        $prevStart = $now->copy()->subDays(2);
        $prevEnd = $now->copy()->subDay();

        $cLast = (clone $base)->whereBetween('occurred_at', [$lastStart, $lastEnd])->count();
        $cPrev = (clone $base)->whereBetween('occurred_at', [$prevStart, $prevEnd])->count();

        if ($cPrev >= 5 && $cLast > $cPrev * 2) {
            $alerts[] = [
                'id'          => 'event_volume_spike',
                'severity'    => 'warning',
                'type'        => 'volume',
                'message'     => 'حجم أحداث المجال في آخر 24 ساعة أكثر من ضعف الـ 24 ساعة السابقة.',
                'detected_at' => $now->toIso8601String(),
                'basis'       => "آخر_24س={$cLast}، سابق_24س={$cPrev}، عتبة: سابق≥5 وأخير>2×سابق",
            ];
        }

        $failQ = EventRecordFailure::query();
        $user = $request->user();
        if ($request->filled('company_id')) {
            $failQ->where('company_id', (int) $request->query('company_id'));
        } elseif ($user && $user->company_id) {
            $failQ->where('company_id', $user->company_id);
        }

        $failSince = $now->copy()->subDays(7);
        $failCount = (clone $failQ)->where('created_at', '>=', $failSince)->count();
        if ($failCount > 0) {
            $alerts[] = [
                'id'          => 'event_record_failures_present',
                'severity'    => 'warning',
                'type'        => 'ingestion',
                'message'     => 'توجد سجلات حديثة في فشل تسجيل الأحداث لهذا النطاق.',
                'detected_at' => $now->toIso8601String(),
                'basis'       => "عدد(failures منذ {$failSince->toDateString()}) = {$failCount}",
            ];
        }

        $inWindow = (clone $base)->whereBetween('occurred_at', [$from, $to])->count();
        if ($inWindow === 0 && config('intelligent.events.persist.enabled')) {
            $alerts[] = [
                'id'          => 'zero_events_while_persist_enabled',
                'severity'    => 'info',
                'type'        => 'coverage',
                'message'     => 'لا أحداث مجال في النافذة المطلوبة رغم تفعيل حفظ الأحداث في الإعدادات.',
                'detected_at' => $now->toIso8601String(),
                'basis'       => 'عدد(أحداث المجال في النافذة)=0 وintelligent.events.persist.enabled=true',
            ];
        }

        return $alerts;
    }
}
