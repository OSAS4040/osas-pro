<?php

namespace App\Http\Controllers\Api\V1\Internal;

use App\Http\Controllers\Controller;
use App\Services\Intelligence\Phase2\Phase2AlertsService;
use App\Services\Intelligence\Phase2\Phase2InsightsService;
use App\Services\Intelligence\Phase2\Phase2OverviewService;
use App\Services\Intelligence\Phase2\Phase2RecommendationsService;
use App\Services\Intelligence\Phase4\Phase4CommandCenterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 2 — read-only intelligence HTTP surface (feature-flagged, admin-only via intelligent.internal).
 */
class Phase2IntelligenceController extends Controller
{
    public function __construct(
        private readonly Phase2OverviewService $overviewService,
        private readonly Phase2InsightsService $insightsService,
        private readonly Phase2RecommendationsService $recommendationsService,
        private readonly Phase2AlertsService $alertsService,
        private readonly Phase4CommandCenterService $commandCenterService,
    ) {}

    public function overview(Request $request): JsonResponse
    {
        $this->assertPhase2Feature('overview');

        return $this->readOnlyJson($this->overviewService->build($request));
    }

    public function insights(Request $request): JsonResponse
    {
        $this->assertPhase2Feature('insights');

        return $this->readOnlyJson($this->insightsService->build($request));
    }

    public function recommendations(Request $request): JsonResponse
    {
        $this->assertPhase2Feature('recommendations');

        return $this->readOnlyJson($this->recommendationsService->build($request));
    }

    public function alerts(Request $request): JsonResponse
    {
        $this->assertPhase2Feature('alerts');

        return $this->readOnlyJson($this->alertsService->build($request));
    }

    /**
     * Phase 4 — Smart Command Center aggregate (read-only composition).
     */
    public function commandCenter(Request $request): JsonResponse
    {
        $canonical = (bool) config('intelligent.command_center_api.enabled');
        $legacy = (bool) config('intelligent.phase2.features.command_center');

        if (! $canonical && ! $legacy) {
            abort(404);
        }

        $data = $this->commandCenterService->build($request);

        return $this->readOnlyJsonPhase4($data);
    }

    private function assertPhase2Feature(string $feature): void
    {
        $canonical = match ($feature) {
            'overview'        => (bool) config('intelligent.overview_api.enabled'),
            'insights'        => (bool) config('intelligent.insights.enabled'),
            'recommendations' => (bool) config('intelligent.recommendations.enabled'),
            'alerts'          => (bool) config('intelligent.alerts.enabled'),
            default           => false,
        };

        $legacy = (bool) config("intelligent.phase2.features.{$feature}");

        if (! $canonical && ! $legacy) {
            abort(404);
        }
    }

    private function readOnlyJson(array $data): JsonResponse
    {
        return response()->json([
            'data'     => $data,
            'meta'     => [
                'read_only' => true,
                'phase'     => 2,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function readOnlyJsonPhase4(array $data): JsonResponse
    {
        $phase = (int) ($data['phase'] ?? 6);

        return response()->json([
            'data'     => $data,
            'meta'     => [
                'read_only' => true,
                'phase'     => $phase,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
