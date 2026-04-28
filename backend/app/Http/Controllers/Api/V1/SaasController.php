<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Services\Platform\PlatformPermissionService;
use App\Services\Saas\SubscriptionAddonEntitlements;
use App\Modules\SubscriptionsV2\Services\SubscriptionService as SubscriptionLifecycleService;
use App\Support\PlanFeatureDefaults;
use Database\Seeders\PlanAddonSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class SaasController extends Controller
{
    public function __construct(
        private readonly PlatformPermissionService $platformPermissionService,
        private readonly SubscriptionLifecycleService $subscriptionLifecycleService,
    ) {}

    // ── Plans (public — no auth required) ────────────────────────────

    public function listPlans(): JsonResponse
    {
        if (! Plan::query()->where('is_active', true)->exists()) {
            (new PlanSeeder)->run();
        }

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        $planAddons = [];
        if (Schema::hasTable('plan_addons')) {
            if (! PlanAddon::query()->where('is_active', true)->exists()) {
                try {
                    (new PlanAddonSeeder)->run();
                } catch (\Throwable $e) {
                    Log::warning('PlanAddonSeeder failed in listPlans', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }
            $planAddons = PlanAddon::query()->where('is_active', true)->orderBy('sort_order')->get();
        } else {
            Log::warning('plan_addons table missing; run: php artisan migrate');
        }

        return response()->json([
            'data'        => $plans,
            'plan_addons' => $planAddons,
            'trace_id'    => app('trace_id'),
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
        $addonsSvc = app(SubscriptionAddonEntitlements::class);

        return response()->json([
            'data' => [
                'subscription'   => $sub,
                'plan'             => $planPayload,
                'active_addons'    => $addonsSvc->activeAddonPayloadForSubscription($sub),
                'is_active'        => in_array($sub->status, ['active', 'trialing']),
                'days_remaining'   => now()->diffInDays($sub->ends_at, false),
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
                $base = $this->featureTokenListToAssoc($fromPlan);
            } else {
                /** @var array<string, bool> $fromPlan */
                $base = $fromPlan;
            }
        } else {
            $fromSub = $sub->features;
            if (is_array($fromSub) && $fromSub !== []) {
                if (array_is_list($fromSub) && isset($fromSub[0]) && is_string($fromSub[0])) {
                    $base = $this->featureTokenListToAssoc($fromSub);
                } else {
                    /** @var array<string, bool> $fromSub */
                    $base = $fromSub;
                }
            } else {
                $base = PlanFeatureDefaults::associativeForSlug((string) $sub->plan);
            }
        }

        return app(SubscriptionAddonEntitlements::class)->mergePurchasedFeatureKeys($sub, $base);
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

        $beforePlan = Plan::where('slug', $sub->plan)->first();
        if ($beforePlan === null) {
            return response()->json(['message' => 'Current plan is not resolvable.'], 422);
        }

        $before = $sub->plan;
        if ((float) $plan->price_monthly >= (float) $beforePlan->price_monthly) {
            $this->subscriptionLifecycleService->upgrade($sub, $plan, (int) $user->id);
            $message = "تم ترقية الباقة من {$before} إلى {$plan->slug}.";
        } else {
            $this->subscriptionLifecycleService->scheduleDowngrade($sub, $plan, (int) $user->id);
            $message = "تمت جدولة تخفيض الباقة من {$before} إلى {$plan->slug} بنهاية الدورة.";
        }

        $fresh = $sub->fresh();
        if ($fresh !== null) {
            app(SubscriptionAddonEntitlements::class)->pruneIneligibleForPlan($fresh);
        }

        return response()->json([
            'data'    => $sub->fresh(),
            'message' => $message,
        ]);
    }

    /**
     * شراء إضافة على الاشتراك الحالي (مالك المستأجر — لا يستبدل الباقة).
     */
    public function purchaseSubscriptionAddon(Request $request): JsonResponse
    {
        if (! Schema::hasTable('plan_addons') || ! Schema::hasTable('subscription_addons')) {
            return response()->json([
                'message'  => 'كتالوج الإضافات غير مهيأ على الخادم. نفِّذ ترحيل قاعدة البيانات (php artisan migrate).',
                'code'     => 'ADDONS_SCHEMA_MISSING',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        $data = $request->validate([
            'addon_slug' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $sub  = Subscription::where('company_id', $user->company_id)->first();
        if (! $sub) {
            return response()->json(['message' => 'لا يوجد اشتراك.'], 404);
        }

        $addon = PlanAddon::query()->where('slug', $data['addon_slug'])->where('is_active', true)->first();
        if (! $addon) {
            return response()->json(['message' => 'الإضافة غير موجودة أو غير مفعّلة.', 'code' => 'ADDON_NOT_FOUND'], 404);
        }

        $svc = app(SubscriptionAddonEntitlements::class);

        try {
            $svc->attach($sub, $addon);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'ADDON_NOT_ELIGIBLE'], 422);
        }

        return response()->json([
            'message' => 'تم تفعيل الإضافة على اشتراككم.',
            'data'    => [
                'active_addons' => $svc->activeAddonPayloadForSubscription($sub->fresh()),
            ],
        ], 201);
    }

    /**
     * إلغاء إضافة مفعّلة على الاشتراك الحالي.
     */
    public function removeSubscriptionAddon(Request $request, string $slug): JsonResponse
    {
        if (! Schema::hasTable('plan_addons') || ! Schema::hasTable('subscription_addons')) {
            return response()->json([
                'message'  => 'كتالوج الإضافات غير مهيأ على الخادم. نفِّذ ترحيل قاعدة البيانات (php artisan migrate).',
                'code'     => 'ADDONS_SCHEMA_MISSING',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        $user = $request->user();
        $sub  = Subscription::where('company_id', $user->company_id)->first();
        if (! $sub) {
            return response()->json(['message' => 'لا يوجد اشتراك.'], 404);
        }

        $addon = PlanAddon::query()->where('slug', $slug)->first();
        if (! $addon) {
            return response()->json(['message' => 'الإضافة غير موجودة.', 'code' => 'ADDON_NOT_FOUND'], 404);
        }

        $svc = app(SubscriptionAddonEntitlements::class);
        $deleted = $svc->detach($sub, $addon);
        if ($deleted === 0) {
            return response()->json(['message' => 'لم تكن هذه الإضافة مفعّلة.', 'code' => 'ADDON_NOT_ATTACHED'], 404);
        }

        return response()->json([
            'message' => 'تم إلغاء الإضافة.',
            'data'    => [
                'active_addons' => $svc->activeAddonPayloadForSubscription($sub->fresh()),
            ],
        ]);
    }

    public function updatePlan(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
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
            'feature_catalog' => ['sometimes', 'array'],
            'feature_catalog.*.key' => ['required_with:feature_catalog', 'string', 'max:80'],
            'feature_catalog.*.name' => ['nullable', 'string', 'max:120'],
            'feature_catalog.*.name_ar' => ['nullable', 'string', 'max:120'],
            'feature_catalog.*.type' => ['required_with:feature_catalog', 'in:main,sub'],
            'feature_catalog.*.price_monthly' => ['nullable', 'numeric', 'min:0'],
            'feature_catalog.*.price_yearly' => ['nullable', 'numeric', 'min:0'],
            'feature_catalog.*.is_enabled' => ['nullable', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ]);

        if (array_key_exists('feature_catalog', $data) && ! array_key_exists('features', $data)) {
            $data['features'] = $this->featuresFromCatalog($data['feature_catalog']);
        }

        $plan->update($data);

        return response()->json([
            'data' => $plan->fresh(),
            'message' => 'تم تحديث الباقة بنجاح.',
        ]);
    }

    public function createPlan(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $this->platformPermissionService->canManageGlobalPlanCatalog($user)) {
            return response()->json([
                'message'  => 'إنشاء باقة جديدة غير مسموح لهذا الحساب.',
                'code'     => 'PLAN_CATALOG_FORBIDDEN',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:80', 'unique:plans,slug'],
            'name' => ['required', 'string', 'max:120'],
            'name_ar' => ['nullable', 'string', 'max:120'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'max_users' => ['required', 'integer', 'min:1'],
            'max_products' => ['required', 'integer', 'min:1'],
            'grace_period_days' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string'],
            'feature_catalog' => ['nullable', 'array'],
            'feature_catalog.*.key' => ['required_with:feature_catalog', 'string', 'max:80'],
            'feature_catalog.*.name' => ['nullable', 'string', 'max:120'],
            'feature_catalog.*.name_ar' => ['nullable', 'string', 'max:120'],
            'feature_catalog.*.type' => ['required_with:feature_catalog', 'in:main,sub'],
            'feature_catalog.*.price_monthly' => ['nullable', 'numeric', 'min:0'],
            'feature_catalog.*.price_yearly' => ['nullable', 'numeric', 'min:0'],
            'feature_catalog.*.is_enabled' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1'],
        ]);

        if (! array_key_exists('features', $data)) {
            $data['features'] = $this->featuresFromCatalog($data['feature_catalog'] ?? []);
        }
        $data['currency'] = strtoupper((string) ($data['currency'] ?? 'SAR'));
        $data['grace_period_days'] = (int) ($data['grace_period_days'] ?? 7);
        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        if (! array_key_exists('sort_order', $data)) {
            $nextSort = (int) (Plan::query()->max('sort_order') ?? 0) + 1;
            $data['sort_order'] = $nextSort > 0 ? $nextSort : 1;
        }

        $plan = Plan::create($data);

        return response()->json([
            'data' => $plan,
            'message' => 'تم إنشاء الباقة بنجاح.',
        ], 201);
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

        if (Schema::hasTable('plan_addons')) {
            (new PlanAddonSeeder)->run();
        } else {
            return response()->json([
                'message'  => 'جدول plan_addons غير موجود. نفِّذ ترحيل قاعدة البيانات أولاً (php artisan migrate).',
                'code'     => 'ADDONS_SCHEMA_MISSING',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        return response()->json(['message' => 'تم إنشاء الباقات الافتراضية.', 'count' => count($plans)]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $catalog
     * @return list<string>
     */
    private function featuresFromCatalog(array $catalog): array
    {
        $features = [];
        foreach ($catalog as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            $enabled = (bool) ($item['is_enabled'] ?? true);
            if ($key !== '' && $enabled) {
                $features[] = $key;
            }
        }

        return array_values(array_unique($features));
    }
}
