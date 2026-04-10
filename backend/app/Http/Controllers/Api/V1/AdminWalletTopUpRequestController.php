<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WalletTopUpRequest;
use App\Services\WalletTopUpRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AdminWalletTopUpRequestController extends Controller
{
    public function __construct(
        private readonly WalletTopUpRequestService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->user()->hasPermission('wallet.top_up_requests.review')) {
            abort(403, 'This action is unauthorized.');
        }

        $q = WalletTopUpRequest::query()
            ->with(['customer:id,name,phone', 'requester:id,name'])
            ->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'returned_for_revision' THEN 1 ELSE 2 END")
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status')->toString());
        }
        if ($request->filled('customer_id')) {
            $q->where('customer_id', (int) $request->input('customer_id'));
        }
        if ($request->filled('payment_method')) {
            $q->where('payment_method', $request->string('payment_method')->toString());
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->date('from')->format('Y-m-d'));
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->date('to')->format('Y-m-d'));
        }

        $paginator = $q->paginate(min((int) $request->input('per_page', 25), 100));

        $data = $paginator->getCollection()->map(function (WalletTopUpRequest $r) {
            return [
                'id' => $r->id,
                'uuid' => $r->uuid,
                'customer' => $r->customer ? ['id' => $r->customer->id, 'name' => $r->customer->name, 'phone' => $r->customer->phone] : null,
                'requester' => $r->requester ? ['id' => $r->requester->id, 'name' => $r->requester->name] : null,
                'target' => $r->target,
                'amount' => (string) $r->amount,
                'currency' => $r->currency,
                'payment_method' => $r->payment_method->value,
                'reference_number' => $r->reference_number,
                'has_receipt' => (bool) $r->receipt_path,
                'status' => $r->status->value,
                'notes_from_customer' => $r->notes_from_customer,
                'review_notes' => $r->review_notes,
                'created_at' => $r->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('review', $row);
        $data = $request->validate([
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $row = $this->service->approve($row, (int) $request->user()->id, $data['note'] ?? null);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json([
            'data' => ['id' => $row->id, 'status' => $row->status->value, 'approved_wallet_transaction_id' => $row->approved_wallet_transaction_id],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('review', $row);
        $data = $request->validate([
            'review_notes' => 'required|string|max:2000',
        ]);

        try {
            $row = $this->service->reject($row, (int) $request->user()->id, $data['review_notes']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status->value], 'trace_id' => app('trace_id')]);
    }

    public function returnForRevision(Request $request, int $id): JsonResponse
    {
        $row = WalletTopUpRequest::query()->findOrFail($id);
        Gate::authorize('review', $row);
        $data = $request->validate([
            'review_notes' => 'required|string|max:2000',
        ]);

        try {
            $row = $this->service->returnForRevision($row, (int) $request->user()->id, $data['review_notes']);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => ['id' => $row->id, 'status' => $row->status->value], 'trace_id' => app('trace_id')]);
    }
}
