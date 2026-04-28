<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CompanyFinancialModel;
use App\Enums\CompanyFinancialModelStatus;
use App\Enums\CompanyStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VerticalProfile;
use App\Models\WorkOrder;
use App\Models\WorkOrderCancellationRequest;
use App\Services\AuditLogger;
use App\Services\Config\VerticalProfileGovernanceService;
use App\Services\NavigationVisibilityService;
use App\Services\Platform\PlatformAdminOverviewService;
use App\Services\Platform\PlatformPermissionService;
use App\Services\Saas\SubscriptionAddonEntitlements;
use App\Services\WorkOrderCancellationRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * واجهات قراءة لمشغّلي المنصة (بريد مُعرَّف في config/saas.php).
 */
class PlatformAdminController extends Controller
{
    public function __construct(
        private readonly WorkOrderCancellationRequestService $workOrderCancellationRequestService,
        private readonly AuditLogger $auditLogger,
        private readonly NavigationVisibilityService $navigationVisibility,
    ) {}

    public function companies(Request $request): JsonResponse
    {
        $paginator = Company::query()
            ->withCount('users')
            ->orderByDesc('id')
            ->paginate(50);

        $paginator->getCollection()->transform(function (Company $c) {
            $sub = Subscription::withoutGlobalScopes()
                ->where('company_id', $c->id)
                ->orderByDesc('id')
                ->first();

            $planSlug = $sub ? (string) $sub->plan : '—';
            $plan     = $planSlug !== '—' ? Plan::where('slug', $planSlug)->first() : null;
            $monthly  = $plan ? (float) $plan->price_monthly : 0.0;
            $planName = $plan !== null
                ? (string) (($plan->name_ar ?? null) ?: ($plan->name ?? $planSlug))
                : '—';

            $owner = User::withoutGlobalScopes()
                ->where('company_id', $c->id)
                ->where('role', UserRole::Owner)
                ->first();

            $status = null;
            if ($sub !== null) {
                $st = $sub->status;
                $status = is_object($st) && property_exists($st, 'value') ? (string) $st->value : (string) $st;
            }

            return [
                'id'                    => $c->id,
                'name'                  => $c->name,
                'slug'                  => 'company-'.$c->id,
                'plan_slug'             => $planSlug,
                'plan_name'             => $planName,
                'is_active'             => (bool) $c->is_active,
                'company_status'        => $c->status?->value,
                'vertical_profile_code' => $c->vertical_profile_code,
                'financial_model'       => $c->financial_model?->value,
                'financial_model_status'=> $c->financial_model_status?->value,
                'credit_limit'          => $c->credit_limit !== null ? (string) $c->credit_limit : null,
                'created_at'            => $c->created_at?->toIso8601String(),
                'updated_at'            => $c->updated_at?->toIso8601String(),
                'owner_name'            => $owner?->name ?? '—',
                'users_count'           => $c->users_count,
                'monthly_revenue'       => $monthly,
                'subscription_status' => $status,
            ];
        });

        return response()->json([
            'data'       => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function globalSearch(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            return response()->json([
                'data' => [
                    'companies' => [],
                    'users' => [],
                    'customers' => [],
                    'invoices' => [],
                    'work_orders' => [],
                ],
                'trace_id' => app('trace_id'),
            ]);
        }

        $limit = min(20, max(3, (int) $request->query('limit', 8)));
        $like = '%'.$q.'%';

        $companies = Company::query()
            ->where('name', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'status']);

        $users = User::withoutGlobalScopes()
            ->where('name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'company_id']);

        $customers = Customer::withoutGlobalScopes()
            ->where('name', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'company_id']);

        $invoices = Invoice::withoutGlobalScopes()
            ->where('invoice_number', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'invoice_number', 'status', 'company_id']);

        $workOrders = WorkOrder::withoutGlobalScopes()
            ->where('order_number', 'like', $like)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'status', 'company_id']);

        return response()->json([
            'data' => [
                'companies' => $companies,
                'users' => $users,
                'customers' => $customers,
                'invoices' => $invoices,
                'work_orders' => $workOrders,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * قائمة عملاء المستأجرين عبر المنصة (قراءة فقط، بلا سياق شركة في الطلب).
     */
    public function platformCustomers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'sometimes|string|max:160',
            'status' => 'sometimes|string|in:active,inactive,all',
            'company_id' => 'sometimes|integer|min:1',
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $perPage = (int) ($data['per_page'] ?? 30);
        $perPage = min(100, max(1, $perPage));

        $query = Customer::query()->withoutGlobalScopes()
            ->with(['company:id,name,status'])
            ->orderByDesc('customers.id');

        if (! empty($data['company_id'] ?? null)) {
            $query->where('customers.company_id', (int) $data['company_id']);
        }

        $status = $data['status'] ?? 'all';
        if ($status === 'active') {
            $query->where('customers.is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('customers.is_active', false);
        }

        $term = isset($data['q']) ? trim($data['q']) : '';
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($w) use ($like): void {
                $w->where('customers.name', 'like', $like)
                    ->orWhere('customers.name_ar', 'like', $like)
                    ->orWhere('customers.phone', 'like', $like)
                    ->orWhere('customers.email', 'like', $like);
            });
        }

        $paginator = $query->paginate($perPage);

        $items = array_map(static function (Customer $c): array {
            $company = $c->company;

            return [
                'id' => $c->id,
                'uuid' => $c->uuid,
                'name' => $c->name_ar !== null && $c->name_ar !== '' ? (string) $c->name_ar : (string) $c->name,
                'company_id' => (int) $c->company_id,
                'company_name' => $company !== null ? (string) $company->name : '—',
                'company_status' => $company?->status?->value,
                'is_active' => (bool) $c->is_active,
                'status_label' => $c->is_active ? 'نشط' : 'غير مفعّل',
                'phone' => $c->phone,
                'email' => $c->email,
                'created_at' => $c->created_at?->toIso8601String(),
            ];
        }, $paginator->items());

        return response()->json([
            'data' => $items,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateFinancialModel(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'decision' => 'required|string|in:approved_prepaid,approved_credit,rejected,suspended',
            'credit_limit' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:2000',
        ]);

        $company = Company::query()->findOrFail($id);

        $reviewerId = $request->user()?->id;

        $beforeFinancial = [
            'financial_model' => $company->financial_model?->value,
            'financial_model_status' => $company->financial_model_status?->value,
            'credit_limit' => $company->credit_limit !== null ? (string) $company->credit_limit : null,
        ];

        $payload = [
            'platform_financial_reviewed_at' => now(),
            'platform_financial_reviewed_by' => $reviewerId,
        ];

        switch ($data['decision']) {
            case 'approved_prepaid':
                $payload['financial_model'] = CompanyFinancialModel::Prepaid;
                $payload['financial_model_status'] = CompanyFinancialModelStatus::ApprovedPrepaid;
                $payload['credit_limit'] = null;
                break;
            case 'approved_credit':
                $payload['financial_model'] = CompanyFinancialModel::Credit;
                $payload['financial_model_status'] = CompanyFinancialModelStatus::ApprovedCredit;
                if (isset($data['credit_limit'])) {
                    $payload['credit_limit'] = $data['credit_limit'];
                }
                break;
            case 'rejected':
                $payload['financial_model'] = null;
                $payload['financial_model_status'] = CompanyFinancialModelStatus::Rejected;
                $payload['credit_limit'] = null;
                break;
            case 'suspended':
                $payload['financial_model_status'] = CompanyFinancialModelStatus::Suspended;
                break;
        }

        if (isset($data['note']) && is_string($data['note']) && $data['note'] !== '') {
            $settings = is_array($company->settings) ? $company->settings : [];
            $settings['platform_financial_review_note'] = $data['note'];
            $payload['settings'] = $settings;
        }

        $company->update($payload);

        $fresh = $company->fresh();
        $this->auditLogger->log(
            action: 'platform.financial_model.updated',
            subjectType: Company::class,
            subjectId: (int) $company->id,
            before: $beforeFinancial,
            after: [
                'decision' => $data['decision'],
                'financial_model' => $fresh?->financial_model?->value,
                'financial_model_status' => $fresh?->financial_model_status?->value,
                'credit_limit' => $fresh?->credit_limit !== null ? (string) $fresh->credit_limit : null,
            ],
            companyId: (int) $company->id,
            branchId: null,
            userId: $reviewerId !== null ? (int) $reviewerId : null,
        );

        return response()->json([
            'data' => [
                'id' => $company->id,
                'financial_model' => $fresh?->financial_model?->value,
                'financial_model_status' => $fresh?->financial_model_status->value,
                'credit_limit' => $fresh?->credit_limit !== null ? (string) $fresh->credit_limit : null,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * ملخص تشغيلي خفيف للمشغّل (قراءة فقط).
     */
    public function opsSummary(Request $request): JsonResponse
    {
        $failedJobs = null;
        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = (int) DB::table('failed_jobs')->count();
        }

        $redisOk = false;
        try {
            $pong = Redis::connection()->ping();
            $redisOk = $pong === true || $pong === '+PONG' || $pong === 'PONG';
        } catch (\Throwable) {
            $redisOk = false;
        }

        $dbOk = false;
        try {
            DB::selectOne('select 1 as ok');
            $dbOk = true;
        } catch (\Throwable) {
            $dbOk = false;
        }

        $queuePending = null;
        if (Schema::hasTable('jobs')) {
            $queuePending = (int) DB::table('jobs')->count();
        }

        return response()->json([
            'data' => [
                'failed_jobs_count' => $failedJobs,
                'redis_ok' => $redisOk,
                'database_ok' => $dbOk,
                'queue_pending_count' => $queuePending,
                'integrity_hint' => 'للمطابقة التشغيلية: `php artisan integrity:sanity` ثم `integrity:verify` عند الحاجة.',
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function showCompany(Request $request, int $id): JsonResponse
    {
        $company = Company::query()->findOrFail($id);
        $sub = Subscription::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->first();

        $planSlug = $sub ? (string) $sub->plan : null;
        $plan = $planSlug ? Plan::where('slug', $planSlug)->first() : null;

        $verticalOptions = VerticalProfile::query()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['code', 'name']);

        return response()->json([
            'data' => [
                'company' => [
                    'id' => $company->id,
                    'name' => $company->name,
                    'is_active' => (bool) $company->is_active,
                    'company_status' => $company->status?->value,
                    'vertical_profile_code' => $company->vertical_profile_code,
                    'financial_model' => $company->financial_model?->value,
                    'financial_model_status' => $company->financial_model_status?->value,
                    'credit_limit' => $company->credit_limit !== null ? (string) $company->credit_limit : null,
                    'created_at' => $company->created_at?->toIso8601String(),
                ],
                'subscription' => $sub ? [
                    'id' => $sub->id,
                    'plan' => (string) $sub->plan,
                    'status' => $sub->status instanceof \BackedEnum ? $sub->status->value : (string) $sub->status,
                    'max_branches' => $sub->max_branches,
                    'max_users' => $sub->max_users,
                ] : null,
                'plan_catalog_match' => $plan ? ['slug' => $plan->slug, 'name' => $plan->name] : null,
                'vertical_profile_options' => $verticalOptions,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function companyEntitySnapshot(Request $request, int $id): JsonResponse
    {
        $company = Company::query()->findOrFail($id);
        $limit = min(25, max(5, (int) $request->query('limit', 10)));

        $users = User::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'email', 'role', 'is_active', 'created_at']);

        $customers = Customer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'name', 'phone', 'email', 'created_at']);

        $vehicles = Vehicle::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'plate_number', 'make', 'model', 'year', 'customer_id', 'created_at']);

        $invoiceAmountColumn = 'total';
        if (Schema::hasColumn('invoices', 'total_amount')) {
            $invoiceAmountColumn = 'total_amount';
        } elseif (Schema::hasColumn('invoices', 'line_total')) {
            $invoiceAmountColumn = 'line_total';
        }

        $invoices = Invoice::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->select(['id', 'invoice_number', 'status', 'customer_id', 'created_at'])
            ->selectRaw($invoiceAmountColumn.' as total_amount')
            ->get();

        $workOrders = WorkOrder::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'order_number', 'status', 'customer_id', 'vehicle_id', 'created_at']);

        return response()->json([
            'data' => [
                'counts' => [
                    'users' => User::withoutGlobalScopes()->where('company_id', $company->id)->count(),
                    'customers' => Customer::withoutGlobalScopes()->where('company_id', $company->id)->count(),
                    'vehicles' => Vehicle::withoutGlobalScopes()->where('company_id', $company->id)->count(),
                    'invoices' => Invoice::withoutGlobalScopes()->where('company_id', $company->id)->count(),
                    'work_orders' => WorkOrder::withoutGlobalScopes()->where('company_id', $company->id)->count(),
                ],
                'users' => $users,
                'customers' => $customers,
                'vehicles' => $vehicles,
                'invoices' => $invoices,
                'work_orders' => $workOrders,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateOperational(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|string|in:active,inactive,suspended',
        ]);

        if (! $request->exists('is_active') && ! $request->exists('status')) {
            return response()->json([
                'message' => 'أرسل is_active و/أو status.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $company = Company::query()->findOrFail($id);

        $before = [
            'is_active' => (bool) $company->is_active,
            'status' => $company->status?->value,
        ];

        $update = [];
        if (array_key_exists('is_active', $data)) {
            $update['is_active'] = $data['is_active'];
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = CompanyStatus::from($data['status']);
        }

        $company->update($update);
        $fresh = $company->fresh();

        $this->auditLogger->log(
            action: 'platform.company.operational_updated',
            subjectType: Company::class,
            subjectId: (int) $company->id,
            before: $before,
            after: [
                'is_active' => (bool) ($fresh?->is_active ?? false),
                'status' => $fresh?->status?->value,
            ],
            companyId: (int) $company->id,
            branchId: null,
            userId: (int) $request->user()->id,
        );

        return response()->json([
            'data' => [
                'id' => $fresh->id,
                'is_active' => (bool) $fresh->is_active,
                'company_status' => $fresh->status->value,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateSubscription(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'plan_slug' => 'required|string|max:64',
        ]);

        $plan = Plan::query()->where('slug', $data['plan_slug'])->where('is_active', true)->firstOrFail();

        Company::query()->findOrFail($id);

        $sub = Subscription::withoutGlobalScopes()
            ->where('company_id', $id)
            ->orderByDesc('id')
            ->first();

        if ($sub === null) {
            return response()->json([
                'message' => 'لا يوجد اشتراك لهذه الشركة لتحديثه.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $before = ['plan' => (string) $sub->plan];

        DB::table('subscriptions')->where('id', $sub->id)->update([
            'plan' => $plan->slug,
            'features' => json_encode($plan->features ?? []),
            'max_branches' => $plan->max_branches,
            'max_users' => $plan->max_users,
            'updated_at' => now(),
        ]);

        $freshSub = Subscription::withoutGlobalScopes()->whereKey($sub->id)->first();
        if ($freshSub !== null) {
            app(SubscriptionAddonEntitlements::class)->pruneIneligibleForPlan($freshSub);
        }

        $this->auditLogger->log(
            action: 'platform.subscription.plan_changed',
            subjectType: Subscription::class,
            subjectId: (int) $sub->id,
            before: $before,
            after: ['plan' => $plan->slug],
            companyId: $id,
            branchId: null,
            userId: (int) $request->user()->id,
        );

        return response()->json([
            'data' => Subscription::withoutGlobalScopes()->whereKey($sub->id)->first(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function syncCompanySubscriptionAddon(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'addon_slug' => ['required', 'string', 'max:120'],
            'attach'     => ['required', 'boolean'],
        ]);

        Company::query()->findOrFail($id);

        $sub = Subscription::withoutGlobalScopes()
            ->where('company_id', $id)
            ->orderByDesc('id')
            ->first();

        if ($sub === null) {
            return response()->json([
                'message'  => 'لا يوجد اشتراك لهذه الشركة.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $addon = PlanAddon::query()->where('slug', $data['addon_slug'])->first();
        if ($addon === null) {
            return response()->json([
                'message'  => 'إضافة غير معروفة.',
                'trace_id' => app('trace_id'),
            ], 404);
        }

        $svc = app(SubscriptionAddonEntitlements::class);

        try {
            if ($data['attach']) {
                $svc->attach($sub, $addon);
            } else {
                $svc->detach($sub, $addon);
            }
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message'  => $e->getMessage(),
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $this->auditLogger->log(
            action: $data['attach'] ? 'platform.subscription.addon_attached' : 'platform.subscription.addon_detached',
            subjectType: Subscription::class,
            subjectId: (int) $sub->id,
            before: [],
            after: [
                'addon_slug' => $data['addon_slug'],
                'attach'     => $data['attach'],
            ],
            companyId: $id,
            branchId: null,
            userId: (int) $request->user()->id,
        );

        return response()->json([
            'data' => [
                'active_addons' => $svc->activeAddonPayloadForSubscription($sub->fresh()),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function storePlanAddonCatalog(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($user)) {
            return response()->json([
                'message'  => 'إنشاء إضافة في الكتالوج غير مسموح لهذا الحساب.',
                'code'     => 'PLAN_CATALOG_FORBIDDEN',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('plan_addons', 'slug')],
            'feature_key' => ['required', 'string', 'max:80', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9_.-]*$/'],
            'name' => ['nullable', 'string', 'max:160'],
            'name_ar' => ['required', 'string', 'max:160'],
            'description_ar' => ['nullable', 'string', 'max:2000'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_yearly' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:8'],
            'eligible_plan_slugs' => ['nullable', 'array'],
            'eligible_plan_slugs.*' => ['string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $eligible = $validated['eligible_plan_slugs'] ?? null;
        if (is_array($eligible) && $eligible === []) {
            $eligible = null;
        }
        if (is_array($eligible) && $eligible !== []) {
            $wanted = array_values(array_unique(array_map('strval', $eligible)));
            $existing = Plan::query()->whereIn('slug', $wanted)->pluck('slug')->map(fn ($s) => (string) $s)->all();
            $missing = array_values(array_diff($wanted, $existing));
            if ($missing !== []) {
                throw ValidationException::withMessages([
                    'eligible_plan_slugs' => ['قيم غير معروفة في كتالوج الباقات: '.implode(', ', $missing)],
                ]);
            }
        }

        $addon = PlanAddon::query()->create([
            'slug' => $validated['slug'],
            'feature_key' => $validated['feature_key'],
            'name' => $validated['name'] ?? null,
            'name_ar' => $validated['name_ar'],
            'description_ar' => $validated['description_ar'] ?? null,
            'price_monthly' => $validated['price_monthly'],
            'price_yearly' => $validated['price_yearly'],
            'currency' => $validated['currency'] ?? 'SAR',
            'eligible_plan_slugs' => $eligible,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'data' => $addon->fresh(),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function updatePlanAddonCatalog(Request $request, string $slug): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! app(PlatformPermissionService::class)->canManageGlobalPlanCatalog($user)) {
            return response()->json([
                'message'  => 'تعديل كتالوج الإضافات غير مسموح لهذا الحساب.',
                'code'     => 'PLAN_CATALOG_FORBIDDEN',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $addon = PlanAddon::query()->where('slug', $slug)->firstOrFail();

        $validated = $request->validate([
            'name_ar'               => ['sometimes', 'string', 'max:160'],
            'description_ar'        => ['sometimes', 'nullable', 'string', 'max:2000'],
            'price_monthly'         => ['sometimes', 'numeric', 'min:0'],
            'price_yearly'          => ['sometimes', 'numeric', 'min:0'],
            'eligible_plan_slugs'   => ['sometimes', 'nullable', 'array'],
            'eligible_plan_slugs.*' => ['string', 'max:64'],
            'is_active'             => ['sometimes', 'boolean'],
            'sort_order'            => ['sometimes', 'integer', 'min:0'],
        ]);

        $addon->update($validated);

        return response()->json([
            'data'     => $addon->fresh(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function assignVerticalProfile(
        Request $request,
        int $id,
        VerticalProfileGovernanceService $governance,
    ): JsonResponse {
        $data = $request->validate([
            'vertical_profile_code' => 'nullable|string|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $company = Company::query()->findOrFail($id);

        try {
            $company = $governance->assignCompanyProfile(
                company: $company,
                verticalProfileCode: $data['vertical_profile_code'] ?? null,
                actorUserId: (int) $request->user()->id,
                reason: $data['reason'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'تعذر تعيين الملف الرأسي.',
                'errors' => $e->errors(),
                'trace_id' => app('trace_id'),
            ], 422);
        }

        return response()->json([
            'data' => [
                'id' => $company->id,
                'vertical_profile_code' => $company->vertical_profile_code,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $q = AuditLog::query()->where('action', 'like', 'platform.%');

        if ($request->filled('company_id')) {
            $q->where('company_id', (int) $request->query('company_id'));
        }

        $paginator = $q->orderByDesc('id')
            ->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        return response()->json([
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function workOrderCancellationRequests(Request $request): JsonResponse
    {
        $status = $request->query('status');

        $q = WorkOrderCancellationRequest::query()
            ->with([
                'requester' => static fn ($rel) => $rel->withoutGlobalScope('tenant'),
                'supportTicket',
            ])
            ->orderByDesc('id');

        if ($status !== null && $status !== '') {
            $q->where('status', $status);
        }

        $paginator = $q->paginate(min(100, max(10, (int) $request->query('per_page', 25))));

        $paginator->getCollection()->transform(function (WorkOrderCancellationRequest $row) {
            $wo = WorkOrder::withoutGlobalScopes()
                ->select(['id', 'company_id', 'order_number', 'status', 'branch_id'])
                ->where('id', $row->work_order_id)
                ->where('company_id', $row->company_id)
                ->first();
            $co = Company::withoutGlobalScopes()->select(['id', 'name'])->where('id', $row->company_id)->first();

            return [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'status' => $row->status->value,
                'reason' => $row->reason,
                'company' => $co ? ['id' => $co->id, 'name' => $co->name] : null,
                'work_order' => $wo ? [
                    'id' => $wo->id,
                    'order_number' => $wo->order_number,
                    'status' => $wo->status->value,
                ] : null,
                'support_ticket_id' => $row->support_ticket_id,
                'requested_by' => $row->requester ? ['id' => $row->requester->id, 'name' => $row->requester->name] : null,
                'created_at' => $row->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'data' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function approveWorkOrderCancellation(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'note' => 'nullable|string|max:2000',
        ]);

        $row = WorkOrderCancellationRequest::query()->findOrFail($id);

        try {
            $row = $this->workOrderCancellationRequestService->approve(
                $row,
                (int) $request->user()->id,
                $data['note'] ?? null,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')]);
    }

    public function rejectWorkOrderCancellation(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'review_notes' => 'required|string|min:3|max:5000',
        ]);

        $row = WorkOrderCancellationRequest::query()->findOrFail($id);

        try {
            $row = $this->workOrderCancellationRequestService->reject(
                $row,
                (int) $request->user()->id,
                $data['review_notes'],
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json(['data' => $row, 'trace_id' => app('trace_id')]);
    }

    /**
     * لوحة قيادة مجمّعة (قراءة فقط) لمشغّلي المنصة.
     */
    public function dashboardOverview(PlatformAdminOverviewService $overviewService): JsonResponse
    {
        return response()->json([
            'data' => $overviewService->build(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function navigationVisibility(): JsonResponse
    {
        return response()->json([
            'data' => $this->navigationVisibility->platformPolicy(),
            'trace_id' => app('trace_id'),
        ]);
    }

    public function updateNavigationVisibility(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'sections' => ['required', 'array'],
            'groups' => ['required', 'array'],
        ]);

        $policy = $this->navigationVisibility->updatePlatformPolicy($payload, (int) $request->user()->id);

        return response()->json([
            'data' => $policy,
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * تعديل كتالوج الباقات العالمي دون تمرير وسيط المستأجر (للمشغّلين الذين لا يملكون company_id).
     */
    public function updatePlanCatalog(Request $request, string $slug): JsonResponse
    {
        return app(SaasController::class)->updatePlan($request, $slug);
    }
}
