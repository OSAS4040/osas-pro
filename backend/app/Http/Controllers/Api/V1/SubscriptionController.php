<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\RenewSubscriptionRequest;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Subscriptions", description="Subscription management")
 */
class SubscriptionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/subscriptions",
     *     tags={"Subscriptions"},
     *     summary="List subscriptions for current company",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $subscriptions = Subscription::orderByDesc('id')->paginate(10);

        return response()->json(['data' => $subscriptions, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/subscriptions/current",
     *     tags={"Subscriptions"},
     *     summary="Get the current active subscription",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function current(Request $request): JsonResponse
    {
        $subscription = Subscription::where('company_id', $request->user()->company_id)
            ->whereIn('status', ['active', 'grace_period'])
            ->latest()
            ->first();

        if (! $subscription) {
            return response()->json(['data' => null, 'message' => 'No active subscription.', 'trace_id' => app('trace_id')]);
        }

        $plan = Plan::findBySlug($subscription->plan);

        return response()->json([
            'data'     => array_merge($subscription->toArray(), ['plan_details' => $plan]),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/subscriptions/renew",
     *     tags={"Subscriptions"},
     *     summary="Renew or upgrade subscription",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"plan"},
     *             @OA\Property(property="plan", type="string", example="professional"),
     *             @OA\Property(property="months", type="integer", example=12)
     *         )
     *     ),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function renew(RenewSubscriptionRequest $request): JsonResponse
    {
        $data       = $request->validated();
        $user       = $request->user();
        $plan       = Plan::findBySlug($data['plan']);
        $months     = $data['months'] ?? 1;

        $current = Subscription::where('company_id', $user->company_id)
            ->whereIn('status', ['active', 'grace_period', 'suspended'])
            ->latest()
            ->first();

        $startsAt = ($current && $current->ends_at->isFuture()) ? $current->ends_at : now();

        $subscription = Subscription::create([
            'uuid'         => Str::uuid(),
            'company_id'   => $user->company_id,
            'plan'         => $plan->slug,
            'status'       => 'active',
            'starts_at'    => $startsAt,
            'ends_at'      => $startsAt->copy()->addMonths($months),
            'amount'       => $plan->price_monthly * $months,
            'currency'     => $data['currency'] ?? $plan->currency,
            'max_branches' => $plan->max_branches,
            'max_users'    => $plan->max_users,
            'features'     => $plan->features,
        ]);

        if ($current) {
            $current->update(['status' => 'suspended']);
        }

        return response()->json(['data' => $subscription, 'trace_id' => app('trace_id')]);
    }
}
