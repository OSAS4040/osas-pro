<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
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
            ->with([
                'creator:id,name',
                'reviewer:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount',
            ])
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
            'description' => 'nullable|string|max:10000',
            'requested_amount' => 'nullable|numeric|min:0',
            'purchase_ids' => 'required|array|min:1|max:50',
            'purchase_ids.*' => 'integer',
        ]);

        $purchaseIds = collect($data['purchase_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        if ($purchaseIds->isEmpty()) {
            return response()->json([
                'message' => 'Select at least one purchase.',
                'code' => 'PURCHASE_IDS_REQUIRED',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $purchases = Purchase::query()
            ->where('company_id', $user->company_id)
            ->where('billing_flow_type', 'platform_to_provider_purchase')
            ->whereIn('id', $purchaseIds)
            ->orderBy('id')
            ->get();

        if ($purchases->count() !== $purchaseIds->count()) {
            return response()->json([
                'message' => 'One or more purchases are invalid or not eligible for payout claims.',
                'code' => 'INVALID_PURCHASE_IDS',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $blockedPurchaseIds = Purchase::query()
            ->where('company_id', $user->company_id)
            ->whereIn('id', $purchaseIds)
            ->whereHas(
                'purchaseClaims',
                fn ($q) => $q->where('purchase_claims.company_id', $user->company_id)
                    ->where(function ($w) {
                        $w->where('purchase_claims.status', 'pending')
                            ->orWhere(function ($inner) {
                                $inner->where('purchase_claims.status', 'approved')
                                    ->where(function ($p) {
                                        $p->whereNull('purchase_claims.platform_review_status')
                                            ->orWhereIn('purchase_claims.platform_review_status', ['pending', 'approved']);
                                    });
                            });
                    })
            )
            ->pluck('id');

        if ($blockedPurchaseIds->isNotEmpty()) {
            return response()->json([
                'message' => 'One or more purchases are already linked to a pending or approved payout claim.',
                'code' => 'PURCHASE_ALREADY_LINKED_TO_CLAIM',
                'purchase_ids' => $blockedPurchaseIds->values()->all(),
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $lines = [];
        foreach ($purchases as $p) {
            $lines[] = sprintf(
                '#%d — %s — %s %s — %s',
                $p->id,
                $p->reference_number ?? '—',
                number_format((float) $p->total, 2, '.', ''),
                $p->currency ?: 'SAR',
                $p->status->value
            );
        }

        $description = 'مطالبة مرتبطة بأوامر شراء التسوية:'."\n".implode("\n", $lines);
        $extra = isset($data['description']) ? trim((string) $data['description']) : '';
        if ($extra !== '') {
            $description .= "\n\nملاحظات إضافية:\n".$extra;
        }

        $requestedAmount = $data['requested_amount'] ?? null;
        if ($requestedAmount === null) {
            $requestedAmount = $purchases->sum(fn (Purchase $p) => max(0.0, (float) $p->total - (float) $p->paid_amount));
        }

        $claim = PurchaseClaim::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $user->company_id,
            'branch_id' => $user->branch_id,
            'created_by_user_id' => $user->id,
            'status' => 'pending',
            'title' => $data['title'] ?? null,
            'description' => $description,
            'requested_amount' => $requestedAmount,
        ]);

        $claim->purchases()->sync($purchaseIds->all());

        return response()->json([
            'data' => $claim->load([
                'creator:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount',
            ]),
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

        $payload = [
            'status' => $data['status'],
            'admin_notes' => $data['admin_notes'] ?? null,
            'reviewed_by_user_id' => $user->id,
            'reviewed_at' => now(),
        ];
        if ($data['status'] === 'approved') {
            $payload['platform_review_status'] = 'pending';
            $payload['platform_review_notes'] = null;
            $payload['platform_reviewed_by_user_id'] = null;
            $payload['platform_reviewed_at'] = null;
        } else {
            $payload['platform_review_status'] = null;
            $payload['platform_review_notes'] = null;
            $payload['platform_reviewed_by_user_id'] = null;
            $payload['platform_reviewed_at'] = null;
        }

        $claim->update($payload);

        return response()->json([
            'data' => $claim->fresh()->load([
                'creator:id,name',
                'reviewer:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount',
            ]),
            'trace_id' => app('trace_id'),
        ]);
    }
}
