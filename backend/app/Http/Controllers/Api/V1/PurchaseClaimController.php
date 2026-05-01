<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PurchaseClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PurchaseClaimController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $q = PurchaseClaim::query()
            ->where('company_id', $user->company_id)
            ->with(['creator:id,name', 'reviewer:id,name'])
            ->orderByDesc('id');

        if (! $user->hasPermission('purchases.claims.review')) {
            $q->where('created_by_user_id', $user->id);
        }

        if ($request->filled('status')) {
            $q->where('status', (string) $request->get('status'));
        }

        $claims = $q->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $claims, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'required|string|max:10000',
            'requested_amount' => 'nullable|numeric|min:0',
        ]);

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'created_by_user_id' => $user->id,
            'status' => 'pending',
            'title' => $data['title'] ?? null,
            'description' => $data['description'],
            'requested_amount' => $data['requested_amount'] ?? null,
        ]);

        return response()->json([
            'data' => $claim->load(['creator:id,name']),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:10000',
        ]);

        $claim = PurchaseClaim::where('company_id', $user->company_id)->findOrFail($id);

        if ($claim->status !== 'pending') {
            return response()->json([
                'message' => 'Claim is not pending review.',
                'code' => 'CLAIM_NOT_PENDING',
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $claim->update([
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
        ]);

        return response()->json([
            'data' => $claim->fresh()->load(['creator:id,name', 'reviewer:id,name']),
            'trace_id' => app('trace_id'),
        ]);
    }
}
