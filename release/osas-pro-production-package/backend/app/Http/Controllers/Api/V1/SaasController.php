<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Platform\PlatformPermissionService;
use App\Support\PlanFeatureDefaults;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaasController extends Controller
{
    public function __construct(
        private readonly PlatformPermissionService $platformPermissionService,
    ) {}

    // ── Plans (public — no auth required) ────────────────────────────

    public function listPlans(): JsonResponse
    {
        if (! Plan::query()->where('is_active', true)->exists()) {
            (new PlanSeeder)->run();
        }

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return response()->json([
            'data'     => $plans,
            'trace_id' => app('trace_id'),
        ]);
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

        $planPayload = $plan?->toArray();
        if ($planPayload === null) {
            $planPayload = [
                'slug'    => $sub->plan,
                'name_ar' => $sub->plan,
            ];
        }
        $planPayload['features'] = $this->resolvePlanFeaturesForResponse($plan, $sub);

        return response()->json([
            'data' => [
                'subscription'   => $sub,
                'plan'           => $planPayload,
                'is_active'      => in_array($sub->status, ['active', 'trialing']),
                'days_remaining' => now()->diffInDays($sub->ends_at, false),
            ],
        ]);
    }

    /**
     * يضمن أن الواجهة تحصل على خريطة ميزات { key: bool } حتى لو غاب صف الباقة أو كانت features فارغة.
     *
     * @return array<string, bool>
     */
    private function resolvePlanFeaturesForResponse(?Plan $plan, Subscription $sub): array
    {
        $fromPlan = $plan?->features;
        if (is_array($fromPlan) && $fromPlan !== []) {
            if (array_is_list($fromPlan) && isset($fromPlan[0]) && is_string($fromPlan[0])) {
                return $this->featureTokenListToAssoc($fromPlan);
            }

            /** @var array<string, bool> $fromPlan */
            return $fromPlan;
        }

        $fromSub = $sub->features;
        if (is_array($fromSub) && $fromSub !== []) {
            if (array_is_list($fromSub) && isset($fromSub[0]) && is_string($fromSub[0])) {
                return $this->featureTokenListToAssoc($fromSub);
            }

            /** @var array<string, bool> $fromSub */
            return $fromSub;
        }

        return PlanFeatureDefaults::associativeForSlug((string) $sub->plan);
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, bool>
     */
    private function featureTokenListToAssoc(array $tokens): array
    {
        $out = [];
        foreach ($tokens as $t) {
            $key       = $t === 'api' ? 'api_access' : $t;
            $out[$key] = true;
        }
        foreach (['pos', 'invoices', 'work_orders', 'fleet', 'reports', 'api_access', 'zatca'] as $k) {
            if (! array_key_exists($k, $out)) {
                $out[$k] = false;
            }
        }

        return $out;
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

    public function updatePlan(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (($user->role ?? null) !== UserRole::Owner) {
            return response()->json(['message' => 'غير مصرح بتعديل الباقات.'], 403);
        }

        if (! $this->platformPermissionService->canManageGlobalPlanCatalog($user)) {
            return response()->json([
                'message'  => 'تعديل كتالوج الباقات العالمي غير مسموح لهذا الحساب. اضبط SAAS_PLATFORM_ADMIN_EMAILS أو SAAS_ALLOW_TENANT_PLAN_CATALOG_EDIT.',
                'code'     => 'PLAN_CATALOG_FORBIDDEN',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $plan = Plan::where('slug', $slug)->firstOrFail();
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'name_ar' => ['sometimes', 'string', 'max:120'],
            'price_monthly' => ['sometimes', 'numeric', 'min:0'],
            'price_yearly' => ['sometimes', 'numeric', 'min:0'],
            'max_branches' => ['sometimes', 'integer', 'min:1'],
            'max_users' => ['sometimes', 'integer', 'min:1'],
            'max_products' => ['sometimes', 'integer', 'min:1'],
            'grace_period_days' => ['sometimes', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        $plan->update($data);

        return response()->json([
            'data' => $plan->fresh(),
            'message' => 'تم تحديث الباقة بنجاح.',
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

    public function seedPlans(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! $this->platformPermissionService->canManageGlobalPlanCatalog($user)) {
            return response()->json([
                'message'  => 'بذر كتالوج الباقات غير مسموح لهذا الحساب.',
                'code'     => 'PLAN_CATALOG_FORBIDDEN',
                'trace_id' => app('trace_id'),
            ], 403);
        }

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
