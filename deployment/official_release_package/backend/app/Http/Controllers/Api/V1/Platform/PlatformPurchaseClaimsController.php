<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Http\Controllers\Controller;
use App\Models\PurchaseClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إشراف المنصة على مطالبات صرف المستحقات عبر المستأجرين.
 */
final class PlatformPurchaseClaimsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = PurchaseClaim::query()
            ->withoutGlobalScope('tenant')
            ->with([
                'company:id,name,status',
                'creator:id,name',
                'reviewer:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount,company_id,billing_flow_type',
            ])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', (string) $request->query('status'));
        }
        if ($request->filled('platform_review_status')) {
            $q->where('platform_review_status', (string) $request->query('platform_review_status'));
        }
        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->query('company_id'));
        }

        $perPage = min(100, max(1, (int) $request->query('per_page', 25)));

        return response()->json([
            'data' => $q->paginate($perPage),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function review(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:approved,rejected',
            'platform_review_notes' => 'nullable|string|max:10000',
        ]);

        $claim = PurchaseClaim::query()
            ->withoutGlobalScope('tenant')
            ->with([
                'company:id,name,status',
                'creator:id,name',
                'reviewer:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount,company_id,billing_flow_type',
            ])
            ->findOrFail($id);

        if ($claim->status !== 'approved') {
            return response()->json([
                'message' => 'Claim must be approved by the tenant before platform review.',
                'code' => 'TENANT_APPROVAL_REQUIRED',
                'trace_id' => app('trace_id'),
            ], 409);
        }

        if ($claim->platform_review_status !== 'pending') {
            return response()->json([
                'message' => 'Claim is not awaiting platform review.',
                'code' => 'CLAIM_NOT_AWAITING_PLATFORM_REVIEW',
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $reviewerId = $request->user()?->id;

        $claim->update([
            'platform_review_status' => $data['status'] === 'approved' ? 'approved' : 'rejected',
            'platform_review_notes' => $data['platform_review_notes'] ?? null,
            'platform_reviewed_by_user_id' => $reviewerId,
            'platform_reviewed_at' => now(),
        ]);

        return response()->json([
            'data' => $claim->fresh()->load([
                'company:id,name,status',
                'creator:id,name',
                'reviewer:id,name',
                'platformReviewer:id,name',
                'purchases:id,reference_number,total,status,currency,paid_amount,company_id,billing_flow_type',
            ]),
            'trace_id' => app('trace_id'),
        ]);
    }
}
