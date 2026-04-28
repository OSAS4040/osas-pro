<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionsV2\RejectPaymentOrderRequest;
use App\Modules\SubscriptionsV2\Actions\ApproveBankTransferAction;
use App\Modules\SubscriptionsV2\Actions\RejectBankTransferAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PlatformPaymentOrderController extends Controller
{
    public function approve(Request $request, int $id, ApproveBankTransferAction $action): JsonResponse
    {
        try {
            $payload = $action->execute($id, (int) $request->user()->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $payload], 200);
    }

    public function reject(RejectPaymentOrderRequest $request, int $id, RejectBankTransferAction $action): JsonResponse
    {
        try {
            $order = $action->execute($id, (int) $request->user()->id, (string) $request->validated('reason'));
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['id' => $order->id, 'status' => $order->status->value]], 200);
    }
}
