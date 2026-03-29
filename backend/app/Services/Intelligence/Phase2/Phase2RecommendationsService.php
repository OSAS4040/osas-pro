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
                'title'    => 'No domain events in the selected window',
                'detail'   => 'If you expect telemetry, confirm Phase 1 flags (INTELLIGENT_EVENTS_ENABLED / INTELLIGENT_EVENTS_PERSIST_ENABLED) and that traffic hits instrumented flows.',
                'basis'    => 'count(domain_events in window) = 0',
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
                    'title'    => 'CustomerCreated dominates the event mix',
                    'detail'   => 'Most recorded events are CustomerCreated. Consider widening the window or enabling persistence on other flows if you need fuller coverage.',
                    'basis'    => 'share(CustomerCreated) >= 75% of events in window',
                ];
            }
        }

        $debits = (int) ($countsByName['WalletDebited'] ?? 0);
        $credits = (int) ($countsByName['WalletCredited'] ?? 0);
        if ($debits > 0 && $credits > 0 && $debits > $credits * 5) {
            $out[] = [
                'id'       => 'wallet_debit_credit_skew',
                'severity' => 'info',
                'title'    => 'WalletDebited events greatly outnumber WalletCredited',
                'detail'   => 'The recorded domain-event ratio shows more debit than credit events in this window. Review whether this matches expected wallet activity (observability only).',
                'basis'    => 'WalletDebited > 5 × WalletCredited in window',
            ];
        }

        $top = $byName[0] ?? null;
        if ($top && $total > 10) {
            $share = ((int) $top['count']) / $total;
            if ($share >= 0.8) {
                $out[] = [
                    'id'       => 'single_event_concentration',
                    'severity' => 'warning',
                    'title'    => 'Very high concentration on one event type',
                    'detail'   => 'One event_name accounts for most traffic. Validate that other critical flows are emitting domain events when persistence is enabled.',
                    'basis'    => 'top event_name share >= 80% with total events > 10',
                ];
            }
        }

        if ($out === []) {
            $out[] = [
                'id'       => 'no_recommendations',
                'severity' => 'info',
                'title'    => 'No notable patterns in the current window',
                'detail'   => 'Heuristics did not flag skew, spikes, or gaps beyond normal variance.',
                'basis'    => 'rule engine — no thresholds triggered',
            ];
        }

        return $out;
    }
}
