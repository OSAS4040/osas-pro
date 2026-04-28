<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformPricingRequest;
use App\Services\Platform\PlatformPricingControlWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PlatformPricingRequestController extends Controller
{
    public function __construct(
        private readonly PlatformPricingControlWorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = PlatformPricingRequest::query()->with(['lines'])->orderByDesc('id');
        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }
        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->query('company_id'));
        }

        return response()->json([
            'data' => $q->paginate(min((int) $request->query('per_page', 25), 100)),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'vehicle_types' => ['nullable', 'array'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.service_code' => ['required', 'string', 'max:64'],
            'lines.*.tenant_service_id' => ['nullable', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0.001'],
            'lines.*.notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $req = $this->workflow->createDraft($request->user(), $data);

        return response()->json([
            'data' => $req->load('lines'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function show(Request $request, string $uuid): JsonResponse
    {
        if (! Str::isUuid($uuid)) {
            return response()->json(['message' => 'Invalid uuid.', 'trace_id' => app('trace_id')], 422);
        }
        $req = PlatformPricingRequest::query()->where('uuid', $uuid)->with(['lines', 'auditLogs'])->firstOrFail();

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function submitForReview(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $req = $this->workflow->submitForReview($req, $request->user());

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function beginReview(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $req = $this->workflow->beginReview($req, $request->user());

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function completeReview(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $body = $request->validate([
            'recommendation' => ['required', 'array'],
            'recommendation.summary' => ['nullable', 'string', 'max:5000'],
            'recommendation.recommended_sell_total' => ['nullable', 'numeric'],
            'recommendation.recommended_provider_id' => ['nullable', 'integer'],
        ]);
        $req = $this->workflow->completeReview($req, $request->user(), $body['recommendation']);

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function escalate(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $req = $this->workflow->escalateToPlatformApproval($req, $request->user());

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function approve(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $data = $request->validate([
            'sell_snapshot' => ['required', 'array', 'min:1'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
        ]);
        $req = $this->workflow->approve($req, $request->user(), $data['sell_snapshot'], $data['contract_id'] ?? null);

        return response()->json(['data' => $req->load('lines'), 'trace_id' => app('trace_id')]);
    }

    public function reject(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $data = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        $req = $this->workflow->reject($req, $request->user(), $data['reason']);

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    public function returnForEdit(Request $request, string $uuid): JsonResponse
    {
        $req = $this->findOrFailUuid($uuid);
        $data = $request->validate([
            'note' => ['required', 'string', 'max:5000'],
        ]);
        $req = $this->workflow->returnForEdit($req, $request->user(), $data['note']);

        return response()->json(['data' => $req, 'trace_id' => app('trace_id')]);
    }

    private function findOrFailUuid(string $uuid): PlatformPricingRequest
    {
        if (! Str::isUuid($uuid)) {
            abort(422, 'Invalid uuid.');
        }

        return PlatformPricingRequest::query()->where('uuid', $uuid)->firstOrFail();
    }
}
