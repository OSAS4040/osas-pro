<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaasController extends Controller
{
    // ── Plans (public — no auth required) ────────────────────────────

    public function listPlans(): JsonResponse
    {
        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();
        return response()->json(['data' => $plans]);
    }

    // ── Current Subscription ──────────────────────────────────────────

    public function currentSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub  = Subscription::where('company_id', $user->company_id)->first();

        if (!$sub) {
            return response()->json(['data' => null, 'message' => 'لا يوجد اشتراك نشط.'], 404);
        }

        $plan = Plan::where('slug', $sub->plan)->first();

        return response()->json([
            'data' => [
                'subscription' => $sub,
                'plan'         => $plan,
                'is_active'    => in_array($sub->status, ['active','trialing']),
                'days_remaining' => now()->diffInDays($sub->ends_at, false),
            ],
        ]);
    }

    // ── Upgrade / Change Plan ─────────────────────────────────────────

    public function changePlan(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['plan_slug' => 'required|string']);

        $plan = Plan::where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();

        $sub = Subscription::where('company_id', $user->company_id)->first();

        if (!$sub) {
            return response()->json(['message' => 'لا يوجد اشتراك لتحديثه.'], 404);
        }

        $before = $sub->plan;
        $sub->update([
            'plan'     => $plan->slug,
            'features' => $plan->features,
            'max_branches' => $plan->max_branches,
            'max_users'    => $plan->max_users,
        ]);

        return response()->json([
            'data'    => $sub->fresh(),
            'message' => "تم تغيير الباقة من {$before} إلى {$plan->slug}.",
        ]);
    }

    // ── Usage & Limits ────────────────────────────────────────────────

    public function usageLimits(Request $request): JsonResponse
    {
        $user = $request->user();
        $sub  = Subscription::where('company_id', $user->company_id)->first();

        $limits = [
            'max_branches' => $sub?->max_branches ?? 1,
            'max_users'    => $sub?->max_users    ?? 5,
        ];

        $usage = [
            'branches' => DB::table('branches')->where('company_id', $user->company_id)->count(),
            'users'    => DB::table('users')->where('company_id', $user->company_id)->count(),
        ];

        $within = [
            'branches' => $usage['branches'] <= $limits['max_branches'],
            'users'    => $usage['users']    <= $limits['max_users'],
        ];

        return response()->json([
            'limits' => $limits,
            'usage'  => $usage,
            'within' => $within,
        ]);
    }

    // ── Seed default plans ────────────────────────────────────────────

    public function seedPlans(): JsonResponse
    {
        $plans = [
            [
                'slug'          => 'starter',
                'name'          => 'Starter',
                'name_ar'       => 'المبتدئ',
                'price_monthly' => 299,
                'price_yearly'  => 2990,
                'currency'      => 'SAR',
                'max_branches'  => 1,
                'max_users'     => 5,
                'max_products'  => 500,
                'grace_period_days' => 3,
                'features'      => ['pos','invoices','work_orders','basic_reports'],
                'is_active'     => true,
                'sort_order'    => 1,
            ],
            [
                'slug'          => 'professional',
                'name'          => 'Professional',
                'name_ar'       => 'الاحترافي',
                'price_monthly' => 699,
                'price_yearly'  => 6990,
                'currency'      => 'SAR',
                'max_branches'  => 3,
                'max_users'     => 20,
                'max_products'  => 5000,
                'grace_period_days' => 7,
                'features'      => ['pos','invoices','work_orders','fleet','wallet','governance','bookings','reports','employees'],
                'is_active'     => true,
                'sort_order'    => 2,
            ],
            [
                'slug'          => 'enterprise',
                'name'          => 'Enterprise',
                'name_ar'       => 'المؤسسي',
                'price_monthly' => 1499,
                'price_yearly'  => 14990,
                'currency'      => 'SAR',
                'max_branches'  => 999,
                'max_users'     => 999,
                'max_products'  => 999999,
                'grace_period_days' => 14,
                'features'      => ['pos','invoices','work_orders','fleet','wallet','governance','bookings','reports','employees','api','zatca','saas_admin'],
                'is_active'     => true,
                'sort_order'    => 3,
            ],
        ];

        foreach ($plans as $p) {
            Plan::updateOrCreate(['slug' => $p['slug']], $p);
        }

        return response()->json(['message' => 'تم إنشاء الباقات الافتراضية.', 'count' => count($plans)]);
    }
}
