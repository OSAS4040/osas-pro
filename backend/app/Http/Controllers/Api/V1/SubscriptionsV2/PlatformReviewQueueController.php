<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionsV2\ManualMatchRequest;
use App\Http\Requests\SubscriptionsV2\RejectPaymentOrderRequest;
use App\Modules\SubscriptionsV2\Actions\ManualMatchAction;
use App\Modules\SubscriptionsV2\Actions\RejectReviewAction;
use App\Modules\SubscriptionsV2\Enums\PaymentOrderStatus;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use App\Modules\SubscriptionsV2\Services\ReconciliationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformReviewQueueController extends Controller
{
    public function index(Request $request, ReconciliationService $reconciliationService): JsonResponse
    {
        $perPage = max(10, min(100, (int) $request->query('per_page', 30)));
        $orders = PaymentOrder::query()
            ->whereIn('status', [PaymentOrderStatus::AwaitingReview, PaymentOrderStatus::Matched])
            ->with([
                'bankTransferSubmissions:id,payment_order_id,amount,transfer_date,bank_name,sender_name,sender_account_masked,bank_reference,receipt_path,status,notes',
                'reconciliationMatches.bankTransaction',
            ])
            ->orderByDesc('id')
            ->paginate($perPage, [
                'id', 'company_id', 'plan_id', 'amount', 'vat', 'total', 'currency', 'reference_code', 'status',
                'expires_at', 'approved_at', 'approved_by', 'created_by', 'created_at', 'updated_at',
            ]);

        $data = $orders->getCollection()->map(function (PaymentOrder $order) use ($reconciliationService) {
            return [
                'payment_order' => $order,
                'matches'       => $order->reconciliationMatches,
                'candidates'    => collect($reconciliationService->findMatches($order))->values(),
            ];
        });
        $orders->setCollection($data);

        return response()->json(['data' => $orders, 'trace_id' => app('trace_id')]);
    }

    public function match(ManualMatchRequest $request, int $id, ManualMatchAction $action): JsonResponse
    {
        try {
            $action->execute($id, (int) $request->validated('bank_transaction_id'), (int) $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['message' => 'ok', 'trace_id' => app('trace_id')]);
    }

    public function reject(RejectPaymentOrderRequest $request, int $id, RejectReviewAction $action): JsonResponse
    {
        try {
            $order = $action->execute($id, (int) $request->user()->id, (string) $request->validated('reason'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['id' => $order->id, 'status' => $order->status->value], 'trace_id' => app('trace_id')]);
    }
}
