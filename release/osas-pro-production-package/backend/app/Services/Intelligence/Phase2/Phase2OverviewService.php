<?php

namespace App\Services\Intelligence\Phase2;

use App\Models\EventRecordFailure;
use Illuminate\Http\Request;

/**
 * Compact internal overview — read-only composition of existing Phase 2 analytics.
 */
final class Phase2OverviewService
{
    public function __construct(
        private readonly Phase2DomainEventQuery $queries,
        private readonly Phase2InsightsService $insights,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(Request $request): array
    {
        [$from, $to] = $this->queries->resolveWindow($request);
        $ins = $this->insights->build($request);

        $failQ = EventRecordFailure::query();
        $user = $request->user();
        if ($request->filled('company_id')) {
            $failQ->where('company_id', (int) $request->query('company_id'));
        } elseif ($user && $user->company_id) {
            $failQ->where('company_id', $user->company_id);
        }
        $failuresInWindow = (clone $failQ)->whereBetween('created_at', [$from, $to])->count();

        return [
            'read_only' => true,
            'phase'     => 2,
            'scope'     => [
                'description' => 'Aggregated from domain_events (+ failure counts from event_record_failures) using SELECT only.',
            ],
            'window'    => $ins['window'],
            'summary'   => [
                'total_domain_events'     => $ins['totals']['events'],
                'distinct_event_names'    => count($ins['by_event_name']),
                'distinct_aggregate_types'=> count($ins['by_aggregate_type']),
                'event_record_failures'   => $failuresInWindow,
            ],
            'endpoints' => [
                'insights'         => '/api/v1/internal/intelligence/insights',
                'recommendations'  => '/api/v1/internal/intelligence/recommendations',
                'alerts'           => '/api/v1/internal/intelligence/alerts',
                'command_center'   => '/api/v1/internal/intelligence/command-center',
            ],
            'feature_flags' => [
                'internal_dashboard' => (bool) config('intelligent.internal_dashboard.enabled'),
                'read_models'          => (bool) config('intelligent.read_models.enabled'),
                'overview_api'         => (bool) config('intelligent.overview_api.enabled')
                    || (bool) config('intelligent.phase2.features.overview'),
                'insights'             => (bool) config('intelligent.insights.enabled')
                    || (bool) config('intelligent.phase2.features.insights'),
                'recommendations'      => (bool) config('intelligent.recommendations.enabled')
                    || (bool) config('intelligent.phase2.features.recommendations'),
                'alerts'               => (bool) config('intelligent.alerts.enabled')
                    || (bool) config('intelligent.phase2.features.alerts'),
                'command_center'       => (bool) config('intelligent.command_center_api.enabled')
                    || (bool) config('intelligent.phase2.features.command_center'),
                'command_center_governance' => (bool) config('intelligent.command_center_governance.enabled'),
                'phase2_master_legacy' => (bool) config('intelligent.phase2.enabled'),
            ],
        ];
    }
}
