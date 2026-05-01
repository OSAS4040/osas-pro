<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkOrderBatch;
use App\Services\WorkOrderBulkSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class WorkOrderBulkController extends Controller
{
    public function __construct(
        private readonly WorkOrderBulkSubmissionService $bulkSubmission,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vehicle_ids' => 'required|array|min:1',
            'vehicle_ids.*' => 'integer|distinct',
            'service_code' => 'required|string|max:64',
            'notes' => 'nullable|string|max:2000',
        ]);

        $user = $request->user();
        $idem = trim((string) $request->header('Idempotency-Key', ''));

        try {
            $result = $this->bulkSubmission->submit(
                (int) $user->company_id,
                (int) $user->branch_id,
                (int) $user->id,
                $data['vehicle_ids'],
                (string) $data['service_code'],
                $data['notes'] ?? null,
                $idem !== '' ? $idem : null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $batch = $result['batch'];
        $batch->refresh();
        $http = $result['replayed'] ? 200 : 202;

        return response()->json([
            'data' => [
                'batch_uuid' => $batch->uuid,
                'status' => $batch->status,
                'vehicle_count' => $batch->items()->count(),
                'chunk_size' => (int) config('work_orders.bulk_chunk_size', 50),
                // Relative to K6_BASE_URL like http://host.docker.internal/api (already includes /api).
                'poll_url' => '/v1/work-orders/batches/'.$batch->uuid,
                'replayed' => $result['replayed'],
            ],
            'trace_id' => app('trace_id'),
        ], $http);
    }

    public function showBatch(Request $request, string $batchUuid): JsonResponse
    {
        if (! Str::isUuid($batchUuid)) {
            return response()->json(['message' => 'Invalid batch uuid.', 'trace_id' => app('trace_id')], 422);
        }

        $user = $request->user();

        $batch = WorkOrderBatch::query()
            ->where('uuid', $batchUuid)
            ->where('company_id', $user->company_id)
            ->withCount([
                'items as pending_items_count' => fn ($q) => $q->where('status', 'pending'),
                'items as succeeded_items_count' => fn ($q) => $q->where('status', 'succeeded'),
                'items as failed_items_count' => fn ($q) => $q->where('status', 'failed'),
            ])
            ->firstOrFail();

        return response()->json([
            'data' => [
                'uuid' => $batch->uuid,
                'status' => $batch->status,
                'source' => $batch->source,
                'bulk_service_code' => $batch->bulk_service_code,
                'notes' => $batch->notes,
                'created_at' => $batch->created_at?->toIso8601String(),
                'counts' => [
                    'total' => $batch->items()->count(),
                    'pending' => (int) $batch->pending_items_count,
                    'succeeded' => (int) $batch->succeeded_items_count,
                    'failed' => (int) $batch->failed_items_count,
                ],
            ],
            'trace_id' => app('trace_id'),
        ]);
    }
}
