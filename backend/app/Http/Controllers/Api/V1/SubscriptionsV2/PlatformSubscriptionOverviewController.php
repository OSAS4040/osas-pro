<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionsV2\Models\RealtimeEvent;
use App\Modules\SubscriptionsV2\Services\AdminSubscriptionsQueryService;
use App\Modules\SubscriptionsV2\Services\InsightsService;
use Illuminate\Http\JsonResponse;

final class PlatformSubscriptionOverviewController extends Controller
{
    public function overview(AdminSubscriptionsQueryService $queryService): JsonResponse
    {
        return response()->json([
            'data' => $queryService->overview(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function transactions(AdminSubscriptionsQueryService $queryService): JsonResponse
    {
        $rows = $queryService->transactions(50);

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }

    public function wallets(AdminSubscriptionsQueryService $queryService): JsonResponse
    {
        $data = $queryService->wallets(50, 100);

        return response()->json([
            'data' => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function insights(InsightsService $insightsService): JsonResponse
    {
        return response()->json([
            'data' => [
                'revenue' => $insightsService->getRevenueSummary(),
                'churn_signals' => $insightsService->getChurnSignals(),
                'risks' => $insightsService->getRiskySubscriptions(),
                'wallet_insights' => $insightsService->getWalletCoverageInsights(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function notifications(): JsonResponse
    {
        $rows = RealtimeEvent::query()
            ->where('audience', 'admin')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }
}

