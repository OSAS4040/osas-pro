<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SubscriptionsV2;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubscriptionsV2\StorePaymentOrderRequest;
use App\Http\Requests\SubscriptionsV2\SubmitBankTransferRequest;
use App\Http\Requests\SubscriptionsV2\UploadReceiptRequest;
use App\Modules\SubscriptionsV2\Actions\CreatePaymentOrderAction;
use App\Modules\SubscriptionsV2\Actions\SubmitBankTransferAction;
use App\Modules\SubscriptionsV2\Actions\UploadReceiptAction;
use App\Modules\SubscriptionsV2\Models\PaymentOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantPaymentOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $rows = PaymentOrder::query()
            ->where('company_id', (int) $user->company_id)
            ->with(['plan:id,name,name_ar,slug'])
            ->orderByDesc('id')
            ->limit(80)
            ->get([
                'id',
                'company_id',
                'plan_id',
                'amount',
                'vat',
                'total',
                'currency',
                'reference_code',
                'status',
                'expires_at',
                'approved_at',
                'created_at',
            ]);

        return response()->json([
            'data' => $rows,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(StorePaymentOrderRequest $request, CreatePaymentOrderAction $action): JsonResponse
    {
        $user  = $request->user();
        $order = $action->execute((int) $user->company_id, (int) $request->validated('plan_id'), (int) $user->id);

        return response()->json(['data' => $order], 201);
    }

    public function submitTransfer(SubmitBankTransferRequest $request, int $id, SubmitBankTransferAction $action): JsonResponse
    {
        $user  = $request->user();
        $order = PaymentOrder::query()->whereKey($id)->where('company_id', $user->company_id)->firstOrFail();
        try {
            $submission = $action->execute($order, $request->validated(), (int) $user->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['submission_id' => $submission->id]], 200);
    }

    public function uploadReceipt(UploadReceiptRequest $request, int $id, UploadReceiptAction $action): JsonResponse
    {
        $user  = $request->user();
        $order = PaymentOrder::query()->whereKey($id)->where('company_id', $user->company_id)->firstOrFail();
        try {
            $submission = $action->execute(
                $order,
                $request->file('receipt'),
                $request->validated('bank_reference'),
                $request->validated('notes'),
                (int) $user->id,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['submission_id' => $submission->id, 'receipt_path' => $submission->receipt_path]], 200);
    }
}
