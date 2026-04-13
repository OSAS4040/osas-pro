<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderBatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkOrderBatchController extends Controller
{
    public function __construct(
        private readonly WorkOrderBatchService $batchService,
        private readonly SensitivePreviewTokenService $previewTokens,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sensitive_preview_token' => 'required|string',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:200',
            'lines.*.customer_id' => 'required|integer|exists:customers,id',
            'lines.*.vehicle_id' => 'required|integer|exists:vehicles,id',
            'lines.*.items' => 'nullable|array',
        ]);

        $user = $request->user();
        $companyId = (int) $user->company_id;
        $userId = (int) $user->id;

        $fingerprint = SensitivePreviewTokenService::fingerprintBatchLines($data['lines']);

        try {
            $this->previewTokens->assertValid(
                $data['sensitive_preview_token'],
                $companyId,
                $userId,
                SensitivePreviewTokenService::OP_BATCH_CREATE,
                [],
                $fingerprint,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        try {
            $batch = $this->batchService->processBatch(
                $companyId,
                (int) $user->branch_id,
                $userId,
                $data['lines'],
                $data['notes'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $this->previewTokens->invalidate($data['sensitive_preview_token']);

        return response()->json(['data' => $batch, 'trace_id' => app('trace_id')], 201);
    }
}
