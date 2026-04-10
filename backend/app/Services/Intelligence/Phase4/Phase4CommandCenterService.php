<?php

namespace App\Services\Intelligence\Phase4;

use App\Services\Intelligence\Phase2\Phase2AlertsService;
use App\Services\Intelligence\Phase2\Phase2DomainEventQuery;
use App\Services\Intelligence\Phase2\Phase2InsightsService;
use App\Services\Intelligence\Phase2\Phase2RecommendationsService;
use App\Services\Intelligence\Phase6\CommandCenterExplainability;
use App\Services\Intelligence\Phase7\CommandCenterGovernanceRef;
use App\Services\Intelligence\Phase7\CommandCenterGovernanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Phase 4/6/7 — read-only command zones + explainability + optional governance hints. No domain writes here.
 * Buckets Phase 2 alerts + recommendations; attaches refs + structured explanations.
 */
final class Phase4CommandCenterService
{
    private const MAX_PER_ZONE = 5;

    public function __construct(
        private readonly Phase2InsightsService $insights,
        private readonly Phase2AlertsService $alerts,
        private readonly Phase2RecommendationsService $recommendations,
        private readonly Phase2DomainEventQuery $domainEventQuery,
        private readonly CommandCenterGovernanceService $governance,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        $snapshotGeneratedAt = now()->toIso8601String();

        $insights = $this->insights->build($request);
        $rawAlerts = $this->alerts->build($request);
        $rawRecs = $this->recommendations->build($request);
        $refPool = $this->buildEntityRefPool($request, $insights);

        $now = [];
        $next = [];
        $watch = [];

        foreach ($rawAlerts as $a) {
            $item = $this->normalizeAlert($a, $refPool, $insights);
            if (($a['severity'] ?? '') === 'warning') {
                $now[] = $item;
            } else {
                $watch[] = $item;
            }
        }

        foreach ($rawRecs as $r) {
            $item = $this->normalizeRecommendation($r, $refPool, $insights);
            $sev = $r['severity'] ?? 'info';
            $id = $r['id'] ?? '';

            if ($sev === 'warning') {
                $now[] = $item;
            } elseif (in_array($id, ['no_events_in_window', 'no_recommendations'], true)) {
                $watch[] = $item;
            } else {
                $next[] = $item;
            }
        }

        $now = array_slice($now, 0, self::MAX_PER_ZONE);
        $next = array_slice($next, 0, self::MAX_PER_ZONE);
        $watch = array_slice($watch, 0, self::MAX_PER_ZONE);

        [$now, $next, $watch] = $this->enrichGovernance(
            $request,
            $insights,
            $now,
            $next,
            $watch,
        );

        $totalSignals = count($now) + count($next) + count($watch);

        return [
            'read_only'     => true,
            'phase'         => 6,
            'generated_at'  => $snapshotGeneratedAt,
            'window'        => $insights['window'],
            'summary'       => [
                'total_now'   => count($now),
                'total_next'  => count($next),
                'total_watch' => count($watch),
                'low_signal'  => $totalSignals <= 1,
            ],
            'zones' => [
                'now'   => $now,
                'next'  => $next,
                'watch' => $watch,
            ],
            'insights_snapshot' => [
                'total_events'       => (int) ($insights['totals']['events'] ?? 0),
                'last_occurred_at'   => $insights['last_occurred_at'] ?? null,
                'first_occurred_at'  => $insights['first_occurred_at'] ?? null,
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $now
     * @param  list<array<string, mixed>>  $next
     * @param  list<array<string, mixed>>  $watch
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>, 2: list<array<string, mixed>>}
     */
    private function enrichGovernance(
        Request $request,
        array $insights,
        array $now,
        array $next,
        array $watch,
    ): array {
        $user = $request->user();
        if (! (bool) config('intelligent.command_center_governance.enabled') || ! $user || ! $user->company_id) {
            $stamp = function (array $items): array {
                return array_map(function (array $item): array {
                    return array_merge($item, [
                        'governance_ref'           => null,
                        'latest_governance_action'   => null,
                        'latest_governance_at'     => null,
                        'latest_governance_by'       => null,
                    ]);
                }, $items);
            };

            return [$stamp($now), $stamp($next), $stamp($watch)];
        }

        $companyId = (int) $user->company_id;
        $window    = $insights['window'] ?? [];
        $wf        = (string) ($window['from'] ?? '');
        $wt        = (string) ($window['to'] ?? '');

        $withRefs = function (array $items, string $zone) use ($companyId, $wf, $wt): array {
            $out = [];
            foreach ($items as $item) {
                $fp = CommandCenterGovernanceRef::signalFingerprint(
                    (string) ($item['id'] ?? ''),
                    (string) ($item['source'] ?? ''),
                    $zone,
                    (string) ($item['title'] ?? ''),
                    (string) ($item['severity'] ?? ''),
                );
                $item['governance_ref'] = CommandCenterGovernanceRef::encode(
                    $companyId,
                    $zone,
                    (string) ($item['source'] ?? ''),
                    (string) ($item['id'] ?? ''),
                    $wf,
                    $wt,
                    $fp,
                    (string) ($item['title'] ?? ''),
                    (string) ($item['severity'] ?? ''),
                );
                $out[] = $item;
            }

            return $out;
        };

        $now   = $withRefs($now, 'now');
        $next  = $withRefs($next, 'next');
        $watch = $withRefs($watch, 'watch');

        $refs = [];
        foreach (array_merge($now, $next, $watch) as $item) {
            if (! empty($item['governance_ref'])) {
                $refs[] = $item['governance_ref'];
            }
        }
        $latest = $this->governance->latestSummariesForRefs($companyId, $refs);

        $apply = function (array $items) use ($latest): array {
            foreach ($items as &$item) {
                $ref = $item['governance_ref'] ?? null;
                if ($ref !== null && isset($latest[$ref])) {
                    $item['latest_governance_action'] = $latest[$ref]['action'];
                    $item['latest_governance_at']     = $latest[$ref]['at'];
                    $item['latest_governance_by']     = $latest[$ref]['by'];
                } else {
                    $item['latest_governance_action'] = null;
                    $item['latest_governance_at']     = null;
                    $item['latest_governance_by']     = null;
                }
            }
            unset($item);

            return $items;
        };

        return [$apply($now), $apply($next), $apply($watch)];
    }

    /**
     * @param  array<string, mixed>  $insights  Phase 2 insights payload
     * @param  list<array{type: string, id: string|int, label: string, href: string}>  $refPool
     * @return array<string, mixed>
     */
    private function normalizeAlert(array $a, array $refPool, array $insights): array
    {
        $severity = (string) ($a['severity'] ?? 'info');
        $message = (string) ($a['message'] ?? $a['type'] ?? 'Alert');

        $item = [
            'id'                         => (string) ($a['id'] ?? ''),
            'source'                     => 'alert',
            'severity'                   => $severity,
            'title'                      => $this->truncate($message, 140),
            'why_now'                    => (string) ($a['basis'] ?? ''),
            'suggested_action'           => $message,
            'impact_if_ignored'          => $this->impactForSeverity($severity),
            'related_entity_references'  => [],
            'meta'                       => [
                'type'         => $a['type'] ?? null,
                'detected_at'  => $a['detected_at'] ?? null,
            ],
        ];
        $item['related_entity_references'] = $this->composeRefs($item, $refPool, 'alert', $a);
        $explain = CommandCenterExplainability::forAlert($a, $insights);

        return array_merge($item, $explain);
    }

    /**
     * @param  array<string, mixed>  $insights
     * @param  list<array{type: string, id: string|int, label: string, href: string}>  $refPool
     * @return array<string, mixed>
     */
    private function normalizeRecommendation(array $r, array $refPool, array $insights): array
    {
        $severity = (string) ($r['severity'] ?? 'info');

        $item = [
            'id'                         => (string) ($r['id'] ?? ''),
            'source'                     => 'recommendation',
            'severity'                   => $severity,
            'title'                      => (string) ($r['title'] ?? ''),
            'why_now'                    => (string) ($r['basis'] ?? ''),
            'suggested_action'           => (string) ($r['detail'] ?? ''),
            'impact_if_ignored'          => $this->impactForSeverity($severity),
            'related_entity_references'  => [],
            'meta'                       => [],
        ];
        $item['related_entity_references'] = $this->composeRefs($item, $refPool, 'recommendation', $r);
        $explain = CommandCenterExplainability::forRecommendation($r, $insights);

        return array_merge($item, $explain);
    }

    /**
     * Recent aggregates in the insights window → navigable SPA paths (read-only hints).
     *
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function buildEntityRefPool(Request $request, array $insights): array
    {
        $window = $insights['window'] ?? [];
        if (empty($window['from']) || empty($window['to'])) {
            return [];
        }

        $from = Carbon::parse($window['from']);
        $to = Carbon::parse($window['to']);

        $base = $this->domainEventQuery->scopedBuilder($request);
        $rows = (clone $base)
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw('aggregate_type, aggregate_id, MAX(occurred_at) as last_at')
            ->groupBy('aggregate_type', 'aggregate_id')
            ->orderByDesc('last_at')
            ->limit(20)
            ->get();

        $refs = [];
        foreach ($rows as $row) {
            $type = strtolower((string) $row->aggregate_type);
            $idStr = (string) $row->aggregate_id;
            if ($idStr === '' || ! ctype_digit($idStr)) {
                continue;
            }
            $mapped = $this->mapAggregateToEntityRef($type, (int) $idStr);
            if ($mapped !== null) {
                $refs[] = $mapped;
            }
        }

        return $this->uniqueRefsByHref($refs, 8);
    }

    /**
     * @return array{type: string, id: int, label: string, href: string}|null
     */
    private function mapAggregateToEntityRef(string $aggregateType, int $id): ?array
    {
        return match ($aggregateType) {
            'invoice' => [
                'type'  => 'invoice',
                'id'    => $id,
                'label' => "فاتورة #{$id}",
                'href'  => "/invoices/{$id}",
            ],
            'work_order' => [
                'type'  => 'work_order',
                'id'    => $id,
                'label' => "أمر عمل #{$id}",
                'href'  => "/work-orders/{$id}",
            ],
            'vehicle' => [
                'type'  => 'vehicle',
                'id'    => $id,
                'label' => "مركبة #{$id}",
                'href'  => "/vehicles/{$id}",
            ],
            'customer' => [
                'type'  => 'customer',
                'id'    => $id,
                'label' => "عميل #{$id}",
                'href'  => '/customers',
            ],
            'wallet_transaction' => [
                'type'  => 'wallet_transaction',
                'id'    => $id,
                'label' => 'المحفظة',
                'href'  => '/wallet',
            ],
            default => null,
        };
    }

    /**
     * @param  list<array{type: string, id: string|int, label: string, href: string}>  $pool
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function composeRefs(array $item, array $pool, string $sourceKind, ?array $raw = null): array
    {
        $refs = [];

        if ($sourceKind === 'alert' && is_array($raw)) {
            $refs = array_merge($refs, $this->staticRefsForAlert($raw));
        }
        if ($sourceKind === 'recommendation' && is_array($raw)) {
            $refs = array_merge($refs, $this->staticRefsForRecommendation($raw));
        }

        $refs = array_merge($refs, $this->scorePoolRefsForItem($item, $pool));

        return array_slice($this->uniqueRefsByHref($refs, 12), 0, 3);
    }

    /**
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function staticRefsForAlert(array $a): array
    {
        return match ($a['id'] ?? '') {
            'event_record_failures_present' => [[
                'type'  => 'governance',
                'id'    => 'event-ingestion',
                'label' => 'الحوكمة والسياسات',
                'href'  => '/governance',
            ]],
            'zero_events_while_persist_enabled' => [[
                'type'  => 'settings_integrations',
                'id'    => 'telemetry',
                'label' => 'التكاملات',
                'href'  => '/settings/integrations',
            ]],
            default => [],
        };
    }

    /**
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function staticRefsForRecommendation(array $r): array
    {
        $id = $r['id'] ?? '';

        if (in_array($id, ['no_events_in_window', 'no_recommendations'], true)) {
            return [[
                'type'  => 'command_center',
                'id'    => 'intelligence',
                'label' => 'مركز العمليات الذكي',
                'href'  => '/internal/intelligence',
            ]];
        }

        if ($id === 'wallet_debit_credit_skew') {
            return [[
                'type'  => 'wallet',
                'id'    => 'summary',
                'label' => 'المحفظة',
                'href'  => '/wallet',
            ]];
        }

        return [];
    }

    /**
     * @param  list<array{type: string, id: string|int, label: string, href: string}>  $pool
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function scorePoolRefsForItem(array $item, array $pool): array
    {
        if ($pool === []) {
            return [];
        }

        $haystack = strtolower(
            $item['title'].' '.$item['why_now'].' '.$item['suggested_action'].' '.$item['id']
        );

        $scored = [];
        foreach ($pool as $ref) {
            $score = 0;
            $t = $ref['type'];
            if (str_contains($haystack, 'wallet') && $t === 'wallet_transaction') {
                $score += 4;
            }
            if ((str_contains($haystack, 'invoice') || str_contains($haystack, 'فاتورة')) && $t === 'invoice') {
                $score += 3;
            }
            if ((str_contains($haystack, 'work') || str_contains($haystack, 'order') || str_contains($haystack, 'أمر')) && $t === 'work_order') {
                $score += 3;
            }
            if (str_contains($haystack, 'customer') && $t === 'customer') {
                $score += 2;
            }
            if (str_contains($haystack, 'vehicle') && $t === 'vehicle') {
                $score += 2;
            }
            $scored[] = ['score' => $score, 'ref' => $ref];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        $picked = [];
        foreach ($scored as $row) {
            if ($row['score'] > 0) {
                $picked[] = $row['ref'];
            }
        }

        if ($picked === []) {
            foreach (array_slice($pool, 0, 2) as $ref) {
                $picked[] = $ref;
            }
        }

        return $picked;
    }

    /**
     * @param  list<array{type: string, id: string|int, label: string, href: string}>  $refs
     * @return list<array{type: string, id: string|int, label: string, href: string}>
     */
    private function uniqueRefsByHref(array $refs, int $max): array
    {
        $seen = [];
        $out = [];
        foreach ($refs as $r) {
            $href = $r['href'] ?? '';
            if ($href === '' || isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $out[] = $r;
            if (count($out) >= $max) {
                break;
            }
        }

        return $out;
    }

    private function impactForSeverity(string $severity): string
    {
        return match ($severity) {
            'warning' => 'قد تبقى نقاط عمياء تشغيلية؛ قد تمر الشذوذات أو مشاكل الالتقاط دون ملاحظة.',
            'critical' => 'خطر أعلى لتفويت حوادث أو فجوات امتثال إن لم تُراجع.',
            default => 'أولوية أقل؛ قد يبقى عمق المراقبة أو غنى الإشارة محدوداً.',
        };
    }

    private function truncate(string $text, int $max): string
    {
        $text = trim($text);
        if (strlen($text) <= $max) {
            return $text;
        }

        return substr($text, 0, $max - 1).'…';
    }
}
