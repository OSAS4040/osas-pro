<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderCancellationRequest;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderCancellationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderCancellationRequestController extends Controller
{
    public function __construct(
        private readonly WorkOrderCancellationRequestService $service,
        private readonly SensitivePreviewTokenService $previewTokens,
    ) {}

    public function store(Request $request, int $workOrderId): JsonResponse
    {
        $data = $request->validate([
            'sensitive_preview_token' => 'required|string',
            'reason' => 'required|string|min:3|max:5000',
        ]);

        $user = $request->user();
        $companyId = (int) $user->company_id;

        $wo = WorkOrder::query()->where('company_id', $companyId)->findOrFail($workOrderId);

        try {
            $this->previewTokens->assertValid(
                $data['sensitive_preview_token'],
                $companyId,
                (int) $user->id,
                SensitivePreviewTokenService::OP_CANCELLATION_REQUEST,
                [$workOrderId],
                null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        try {
            $row = $this->service->submit($wo, (int) $user->id, $data['reason']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $this->previewTokens->invalidate($data['sensitive_preview_token']);

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')], 201);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $row = WorkOrderCancellationRequest::query()->findOrFail($id);

        try {
            $row = $this->service->approve($row, (int) $request->user()->id, $data['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'review_notes' => 'required|string|min:3|max:5000',
        ]);

        $row = WorkOrderCancellationRequest::query()->findOrFail($id);

        try {
            $row = $this->service->reject($row, (int) $request->user()->id, $data['review_notes']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')]);
    }
}
