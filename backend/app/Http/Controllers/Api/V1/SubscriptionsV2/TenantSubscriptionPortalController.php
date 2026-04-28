<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Modules\SubscriptionsV2\Models\RealtimeEvent;
use App\Modules\SubscriptionsV2\Services\SubscriptionPortalQueryService;
use App\Modules\SubscriptionsV2\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantSubscriptionPortalController extends Controller
{
    public function current(Request $request, SubscriptionPortalQueryService $queryService): JsonResponse
    {
        $user = $request->user();
        $data = $queryService->current((int) $user->company_id);

        return response()->json([
            'data' => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function plans(SubscriptionPortalQueryService $queryService): JsonResponse
    {
        $plans = $queryService->plans();

        return response()->json(['data' => $plans, 'trace_id' => app('trace_id')]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $user = $request->user();
        $invoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $user->company_id)
            ->where('type', 'subscription')
            ->orderByDesc('id')
            ->limit(100)
            ->get(['id', 'invoice_number', 'status', 'total', 'paid_amount', 'due_amount', 'currency', 'issued_at', 'due_at']);

        return response()->json(['data' => $invoices, 'trace_id' => app('trace_id')]);
    }

    public function wallet(Request $request, SubscriptionPortalQueryService $queryService): JsonResponse
    {
        $user = $request->user();
        $data = $queryService->wallet((int) $user->company_id);

        return response()->json([
            'data' => $data,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function upgrade(Request $request, SubscriptionService $subscriptionService): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validate(['plan_slug' => ['required', 'string']]);
        $subscription = Subscription::withoutGlobalScopes()->where('company_id', $user->company_id)->latest('id')->firstOrFail();
        $plan = Plan::query()->where('slug', (string) $payload['plan_slug'])->where('is_active', true)->firstOrFail();
        $change = $subscriptionService->upgrade($subscription, $plan, (int) $user->id);

        return response()->json(['data' => $change, 'trace_id' => app('trace_id')], 201);
    }

    public function downgrade(Request $request, SubscriptionService $subscriptionService): JsonResponse
    {
        $user = $request->user();
        $payload = $request->validate(['plan_slug' => ['required', 'string']]);
        $subscription = Subscription::withoutGlobalScopes()->where('company_id', $user->company_id)->latest('id')->firstOrFail();
        $plan = Plan::query()->where('slug', (string) $payload['plan_slug'])->where('is_active', true)->firstOrFail();
        $change = $subscriptionService->scheduleDowngrade($subscription, $plan, (int) $user->id);

        return response()->json(['data' => $change, 'trace_id' => app('trace_id')], 201);
    }

    public function notifications(Request $request): JsonResponse
    {
        $user = $request->user();
        $afterId = (int) $request->query('after_id', 0);
        $rows = RealtimeEvent::query()
            ->where('audience', 'company')
            ->where('company_id', (int) $user->company_id)
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }
}

