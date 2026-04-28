<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Modules\SubscriptionsV2\Services\PlatformSubscriptionAttentionService;
use App\Modules\SubscriptionsV2\Services\PlatformSubscriptionOperationsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * قراءة فقط — تفاصيل تشغيل اشتراكات المنصة (لا تعديلات مالية).
 */
final class PlatformSubscriptionOperationsController extends Controller
{
    public function attentionSummary(PlatformSubscriptionAttentionService $attention): JsonResponse
    {
        return response()->json([
            'data' => $attention->summary(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function subscriptionList(Request $request, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        $perPage = max(10, min(100, (int) $request->query('per_page', 30)));

        return response()->json([
            'data' => $svc->paginateSubscriptionDirectory($perPage),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function subscriptionShow(int $subscription, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        return response()->json([
            'data' => $svc->subscriptionDetail($subscription),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function paymentOrderShow(int $id, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        return response()->json([
            'data' => $svc->paymentOrderDetail($id),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function invoiceList(Request $request, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        $perPage = max(10, min(100, (int) $request->query('per_page', 30)));

        return response()->json([
            'data' => $svc->paginatePlatformSubscriptionInvoices($perPage),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function invoiceShow(int $invoice, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        return response()->json([
            'data' => $svc->invoiceDetail($invoice),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function bankTransactionShow(int $id, PlatformSubscriptionOperationsQueryService $svc): JsonResponse
    {
        return response()->json([
            'data' => $svc->bankTransactionDetail($id),
            'trace_id' => app('trace_id'),
        ]);
    }
}
