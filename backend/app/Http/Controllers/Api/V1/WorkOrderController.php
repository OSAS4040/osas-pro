<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CustomerWallet;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Services\Config\VerticalBehaviorResolverService;
use App\Services\IntelligentReading\KsaPlateNormalizer;
use App\Services\Messaging\WhatsAppOutboundService;
use App\Services\Ocr\TesseractOcrRunner;
use App\Services\PlatformPricingApprovalGateService;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderPdfService;
use App\Services\WorkOrderPricingResolverService;
use App\Services\WorkOrderService;
use App\Support\Media\TenantUploadDisk;
use App\Support\TenantBusinessFeatures;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * @OA\Tag(name="WorkOrders", description="Work order management")
 */
class WorkOrderController extends Controller
{
    public function __construct(
        private readonly WorkOrderService $workOrderService,
        private readonly VerticalBehaviorResolverService $behaviorResolver,
        private readonly SensitivePreviewTokenService $previewTokens,
        private readonly WorkOrderPricingResolverService $pricingResolver,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/work-orders",
     *     tags={"WorkOrders"},
     *     summary="List work orders",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="customer_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="vehicle_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $viewer */
        $viewer = $request->user();
        if ($request->filled('company_id') && (int) $request->company_id !== (int) app('tenant_company_id')) {
            return response()->json([
                'message' => 'company_id does not match your tenant.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        $base = $this->workOrdersBaseQuery($request);

        $statusCounts = null;
        if ($request->boolean('include_status_counts')) {
            $rows = (clone $base)
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->get();
            $statusCounts = [];
            foreach ($rows as $row) {
                $statusCounts[(string) $row->status] = (int) $row->aggregate;
            }
            $statusCounts['all'] = array_sum($statusCounts);
        }

        $perPage = max(1, min((int) ($request->per_page ?? 25), 100));

        $orders = (clone $base)
            ->with(['customer', 'vehicle', 'assignedTechnician', 'branch'])
            ->when($request->filled('status'), function ($q) use ($request): void {
                $raw = (string) $request->status;
                $parts = array_values(array_unique(array_filter(array_map('trim', explode(',', $raw)))));
                $allowed = array_map(static fn (WorkOrderStatus $c): string => $c->value, WorkOrderStatus::cases());
                $parts = array_values(array_intersect($parts, $allowed));
                if ($parts === []) {
                    return;
                }
                if (count($parts) === 1) {
                    $q->where('status', $parts[0]);
                } else {
                    $q->whereIn('status', $parts);
                }
            })
            ->orderByDesc('id')
            ->paginate($perPage);
        $orders->getCollection()->transform(fn (WorkOrder $order): WorkOrder => $this->sanitizeMediaForViewer($order, $viewer));

        $payload = ['data' => $orders, 'trace_id' => app('trace_id')];
        if ($statusCounts !== null) {
            $payload['status_counts'] = $statusCounts;
        }

        return response()->json($payload);
    }

    /**
     * Filters shared by list + status aggregates (excludes status filter so counts reflect search scope).
     */
    private function workOrdersBaseQuery(Request $request): Builder
    {
        $branchScope = $this->workOrderBranchConstraint($request);

        return WorkOrder::query()
            ->when($branchScope !== null, fn ($q) => $q->where((new WorkOrder)->getTable().'.branch_id', $branchScope))
            ->when($request->customer_id, fn ($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->vehicle_id, fn ($q) => $q->where('vehicle_id', $request->vehicle_id))
            ->when($request->technician_id, fn ($q) => $q->where('assigned_technician_id', $request->technician_id))
            ->when($request->filled('search'), function ($q) use ($request) {
                $s = trim((string) $request->search);
                $q->where(function ($q) use ($s) {
                    $q->where('order_number', 'ilike', "%{$s}%")
                        ->orWhere('description', 'ilike', "%{$s}%")
                        ->orWhere('notes', 'ilike', "%{$s}%")
                        ->orWhereHas('vehicle', fn ($vq) => $vq->where('plate_number', 'ilike', "%{$s}%"));
                });
            });
    }

    /**
     * Branch narrowing for work orders: enforced for roles without cross_branch_access.
     * Roles with cross_branch_access see all branches unless the client selects a branch (query / header),
     * which BranchScopeMiddleware maps to tenant_branch_id.
     */
    private function workOrderBranchConstraint(Request $request): ?int
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user || ! app()->has('tenant_branch_id')) {
            return null;
        }
        $branchId = (int) app('tenant_branch_id');
        if ($user->hasPermission('cross_branch_access')) {
            if ($request->filled('branch_id') || $request->header('X-Branch-Id')) {
                return $branchId;
            }

            return null;
        }

        return $branchId;
    }

    public function intakeLookup(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'order_number' => ['nullable', 'string', 'max:80'],
            'plate_number' => ['nullable', 'string', 'max:40'],
            'on_behalf_company_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $orderNumber = trim((string) ($data['order_number'] ?? ''));
        $plateNumber = trim((string) ($data['plate_number'] ?? ''));
        if ($orderNumber === '' && $plateNumber === '') {
            return response()->json([
                'message' => 'يجب إدخال رقم أمر العمل أو رقم اللوحة.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $resolved = $this->tryResolveIntakeTenantCompany($request, $user);
        if ($resolved instanceof JsonResponse) {
            return $resolved;
        }

        $branchConstraint = $resolved['delegated_by_platform'] ? null : $this->workOrderBranchConstraint($request);

        return response()->json(
            $this->buildIntakeLookupPayload(
                $user,
                $orderNumber !== '' ? $orderNumber : null,
                $plateNumber !== '' ? $plateNumber : null,
                $branchConstraint,
                $resolved['company_id'],
                $resolved['delegated_by_platform'],
            )
        );
    }

    public function intakeLookupCamera(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'image' => ['nullable', 'string'],
            'plate_number' => ['nullable', 'string', 'max:40'],
            'order_number' => ['nullable', 'string', 'max:80'],
            'on_behalf_company_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $orderNumber = trim((string) ($data['order_number'] ?? ''));
        $plateFallback = trim((string) ($data['plate_number'] ?? ''));
        $imageBase64 = trim((string) ($data['image'] ?? ''));
        if ($imageBase64 !== '' && str_starts_with($imageBase64, 'data:')) {
            $parts = explode(',', $imageBase64, 2);
            $imageBase64 = trim($parts[1] ?? '');
        }

        if ($orderNumber === '' && $plateFallback === '' && $imageBase64 === '') {
            return response()->json([
                'message' => 'يجب إرسال صورة كاميرا أو رقم لوحة أو رقم أمر عمل.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $resolvedPlate = null;
        $ocrMeta = [
            'used' => false,
            'success' => false,
            'source' => null,
            'error' => null,
            'raw_text' => null,
            'confidence' => 0.0,
            'attempts' => [],
            'candidates' => [],
        ];

        if ($imageBase64 !== '') {
            $ocrMeta['used'] = true;
            $imgData = base64_decode($imageBase64, true);
            if (! $imgData || strlen($imgData) < 100) {
                $ocrMeta['error'] = 'invalid_image';
            } else {
                /** @var TesseractOcrRunner $ocr */
                $ocr = app(TesseractOcrRunner::class);
                $bestConfidence = 0.0;
                $bestCandidate = null;
                $bestRawText = null;
                foreach ([7, 6, 11, 13] as $psm) {
                    $res = $ocr->runRaw($imgData, (string) config('ocr.default_lang_plate', 'eng+ara'), $psm);
                    $rawText = $res['code'] === TesseractOcrRunner::CODE_OK ? (string) ($res['text'] ?? '') : '';
                    $candidate = $rawText !== '' ? KsaPlateNormalizer::normalize($rawText) : null;
                    $confidence = 0.0;
                    if ($candidate !== null) {
                        $confidence = 0.65;
                        if (preg_match('/[A-Z]{3}\s\d{4}/', (string) $candidate['display']) === 1) {
                            $confidence += 0.25;
                        }
                        if (mb_strlen((string) $rawText) <= 20) {
                            $confidence += 0.10;
                        }
                    }
                    $ocrMeta['attempts'][] = [
                        'psm' => $psm,
                        'code' => (string) ($res['code'] ?? 'unknown'),
                        'confidence' => round($confidence, 4),
                    ];
                    if ($candidate !== null) {
                        $ocrMeta['candidates'][] = [
                            'plate' => (string) $candidate['display'],
                            'confidence' => round($confidence, 4),
                        ];
                    }
                    if ($confidence > $bestConfidence && $candidate !== null) {
                        $bestConfidence = $confidence;
                        $bestCandidate = $candidate;
                        $bestRawText = $rawText;
                    }
                }
                if ($bestCandidate !== null) {
                    $resolvedPlate = (string) $bestCandidate['display'];
                    $ocrMeta['success'] = true;
                    $ocrMeta['source'] = 'camera_ocr';
                    $ocrMeta['confidence'] = round($bestConfidence, 4);
                    $ocrMeta['raw_text'] = $bestRawText !== null ? mb_substr($bestRawText, 0, 200) : null;
                } else {
                    $ocrMeta['error'] = 'plate_not_detected';
                }
            }
        }

        if ($resolvedPlate === null && $plateFallback !== '') {
            $resolvedPlate = $plateFallback;
            $ocrMeta['source'] = 'manual_plate';
        }

        $resolvedContext = $this->tryResolveIntakeTenantCompany($request, $user);
        if ($resolvedContext instanceof JsonResponse) {
            return $resolvedContext;
        }
        $cameraBranchConstraint = $resolvedContext['delegated_by_platform'] ? null : $this->workOrderBranchConstraint($request);

        $payload = $this->buildIntakeLookupPayload(
            $user,
            $orderNumber !== '' ? $orderNumber : null,
            $resolvedPlate,
            $cameraBranchConstraint,
            $resolvedContext['company_id'],
            $resolvedContext['delegated_by_platform'],
        );
        $payload['data']['camera_lookup'] = $ocrMeta;
        $payload['data']['arrival'] = [
            'lookup_key' => $orderNumber !== '' ? 'order_number' : 'plate_number',
            'lookup_value' => $orderNumber !== '' ? $orderNumber : $resolvedPlate,
            'has_active_work_order' => (bool) (($payload['data']['work_order']['is_active'] ?? false) === true),
            'provider_can_execute_service' => (bool) ($payload['data']['execution']['provider_can_execute_service'] ?? false),
            'can_execute_now' => (bool) ($payload['data']['execution']['can_execute_now'] ?? false),
            'prepaid_balance_ok' => (bool) ($payload['data']['prepaid']['has_positive_balance'] ?? false),
        ];

        return response()->json($payload);
    }

    /**
     * تقدير قراءة عدّاد (أرقام فقط) من صورة — مساعدة يدوية؛ يُراجع المستخدم قبل الحفظ.
     */
    public function intakeOdometerOcr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'image' => ['required', 'string'],
        ]);
        $rawInput = (string) $data['image'];
        if (str_starts_with($rawInput, 'data:')) {
            $parts = explode(',', $rawInput, 2);
            $rawInput = $parts[1] ?? '';
        }
        $imgData = base64_decode($rawInput, true);
        if ($imgData === false || strlen($imgData) < 80) {
            return response()->json([
                'message' => 'صورة غير صالحة.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        /** @var TesseractOcrRunner $ocr */
        $ocr = app(TesseractOcrRunner::class);
        $meta = ['attempts' => [], 'raw_text' => null, 'success' => false, 'error' => null];
        $bestDigits = null;
        $bestLen = 0;
        $bestConfidence = 0.0;

        foreach ([7, 6, 13, 11] as $psm) {
            $res = $ocr->runRaw($imgData, 'eng', $psm);
            $meta['attempts'][] = ['psm' => $psm, 'code' => $res['code'] ?? 'unknown'];
            if (($res['code'] ?? '') !== TesseractOcrRunner::CODE_OK || ($res['text'] ?? '') === '') {
                continue;
            }
            $text = (string) $res['text'];
            if ($meta['raw_text'] === null) {
                $meta['raw_text'] = mb_substr($text, 0, 200);
            }
            if (preg_match_all('/\d{4,7}/', $text, $matches)) {
                foreach ($matches[0] as $block) {
                    $len = strlen($block);
                    $confidence = min(0.99, 0.5 + $len * 0.06);
                    if ($len > $bestLen || ($len === $bestLen && $confidence > $bestConfidence)) {
                        $bestLen = $len;
                        $bestDigits = (int) $block;
                        $bestConfidence = $confidence;
                    }
                }
            }
        }

        if ($bestDigits !== null) {
            $meta['success'] = true;
        } else {
            $meta['error'] = 'digits_not_detected';
        }

        return response()->json([
            'data' => [
                'suggested_reading' => $bestDigits,
                'confidence' => round($bestConfidence, 4),
                'ocr' => $meta,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @return array{company_id: int, delegated_by_platform: true}|array{company_id: int, delegated_by_platform: false}|JsonResponse
     */
    private function tryResolveIntakeTenantCompany(Request $request, User $user)
    {
        $raw = $request->input('on_behalf_company_id');
        $hasOnBehalf = $raw !== null && $raw !== '' && (int) $raw > 0;
        if (! $hasOnBehalf) {
            if (! $user->company_id) {
                return response()->json([
                    'message' => 'يجب اختيار مزوّد (شريك تنفيذ) من القائمة أعلاه — البحث يتم في سياق شركاء التنفيذ فقط عند العمل نيابةً عنهم.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }

            return [
                'company_id' => (int) $user->company_id,
                'delegated_by_platform' => false,
            ];
        }
        if (! (bool) $user->is_platform_user) {
            return response()->json([
                'message' => 'تنفيذ البحث بالنيابة عن شركة محدّدة مخصّص لمشغّلي المنصّة فقط.',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        if (! $user->hasPermission('platform.companies.read') && ! $user->hasPermission('platform.ops.read')) {
            return response()->json([
                'message' => 'لا تملك صلاحية التحكّم في سياق مزوّد آخر (مطلوب: قراءة المشتركين أو عمليات المنصّة).',
                'trace_id' => app('trace_id'),
            ], 403);
        }
        $cid = (int) $raw;
        $company = Company::query()->find($cid);
        if ($company === null) {
            return response()->json([
                'message' => 'الشركة المحدّدة غير موجودة.',
                'trace_id' => app('trace_id'),
            ], 404);
        }
        if (! TenantBusinessFeatures::isPlatformExecutionPartnerTenant($cid)) {
            return response()->json([
                'message' => 'الشركة المختارة ليست مُسجّلة كشريك تنفيذ منصّة. اختر مزوّداً مُفعّلاً في إعدادات المنصّة.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        return [
            'company_id' => $cid,
            'delegated_by_platform' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIntakeLookupPayload(
        User $user,
        ?string $orderNumber,
        ?string $plateNumber,
        ?int $restrictToBranchId,
        int $targetCompanyId,
        bool $delegatedByPlatform,
    ): array {
        $companyId = $targetCompanyId;
        $tenantCompany = Company::query()->find($companyId);
        if ($tenantCompany === null) {
            return [
                'data' => [
                    'lookup' => [
                        'order_number' => ($orderNumber ?? '') !== '' ? $orderNumber : null,
                        'plate_number' => ($plateNumber ?? '') !== '' ? $plateNumber : null,
                    ],
                    'work_order' => null,
                    'vehicle' => null,
                    'show_service_lines' => false,
                    'service_lines' => [],
                    'prepaid' => [],
                    'execution' => [],
                    'arrival' => [],
                    'delegation' => ['by_platform' => $delegatedByPlatform, 'company_id' => null, 'company_name' => null],
                ],
                'trace_id' => app('trace_id'),
            ];
        }
        $query = WorkOrder::query()
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->with(['vehicle:id,customer_id,plate_number,make,model', 'customer:id,name,type'])
            ->orderByDesc('id');

        if ($restrictToBranchId !== null) {
            $query->where('branch_id', $restrictToBranchId);
        }

        if (($orderNumber ?? '') !== '') {
            $query->where(static function ($q) use ($orderNumber): void {
                $q->where('order_number', $orderNumber)->orWhere('work_order_number', $orderNumber);
            });
        }
        if (($plateNumber ?? '') !== '') {
            $query->whereHas('vehicle', static function ($q) use ($plateNumber, $companyId): void {
                $q->where('vehicles.company_id', $companyId)
                    ->where('plate_number', 'ilike', $plateNumber);
            });
        }

        $order = $query->first();

        $vehicle = $order?->vehicle;
        if ($vehicle === null && ($plateNumber ?? '') !== '') {
            $vQ = Vehicle::query()
                ->where('company_id', $companyId)
                ->where('plate_number', 'ilike', $plateNumber);
            if ($restrictToBranchId !== null) {
                $vQ->where('branch_id', $restrictToBranchId);
            }
            $vehicle = $vQ->first();
        }

        $activeStatuses = [
            WorkOrderStatus::Draft,
            WorkOrderStatus::PendingManagerApproval,
            WorkOrderStatus::Approved,
            WorkOrderStatus::InProgress,
            WorkOrderStatus::OnHold,
            WorkOrderStatus::CancellationRequested,
        ];
        $isActiveOrder = $order !== null && in_array($order->status, $activeStatuses, true);

        $wallets = [];
        $fleetMainBalance = 0.0;
        $vehicleWalletBalance = 0.0;
        $customerMainBalance = 0.0;
        if ($vehicle?->customer_id !== null) {
            $wallets = CustomerWallet::query()
                ->where('company_id', $companyId)
                ->where('customer_id', (int) $vehicle->customer_id)
                ->get();
            foreach ($wallets as $w) {
                $type = $w->wallet_type instanceof \BackedEnum ? $w->wallet_type->value : (string) $w->wallet_type;
                $bal = (float) $w->balance;
                if ($type === 'fleet_main') {
                    $fleetMainBalance += $bal;
                } elseif ($type === 'customer_main') {
                    $customerMainBalance += $bal;
                } elseif ($type === 'vehicle_wallet' && (int) ($w->vehicle_id ?? 0) === (int) ($vehicle->id ?? 0)) {
                    $vehicleWalletBalance += $bal;
                }
            }
        }

        $financialModel = $tenantCompany->financial_model ?? null;
        $financialModelValue = $financialModel instanceof \BackedEnum ? $financialModel->value : (string) $financialModel;
        $isPrepaid = $financialModelValue === 'prepaid';
        $hasPositivePrepaidBalance = ($fleetMainBalance + $vehicleWalletBalance) > 0.0001;
        $hasAnyWalletCredit = ($fleetMainBalance + $vehicleWalletBalance + $customerMainBalance) > 0.0001;
        $isTerminalOrder = $order !== null && in_array($order->status, [
            WorkOrderStatus::Completed,
            WorkOrderStatus::Delivered,
            WorkOrderStatus::Cancelled,
        ], true);
        $showServiceLines = $order !== null && ! $isTerminalOrder && ($isActiveOrder || $hasAnyWalletCredit);
        $serviceLines = [];
        if ($showServiceLines) {
            $order->load(['items' => static fn (HasMany $q) => $q->orderBy('id')]);
            $serviceLines = $order->items->map(static function (WorkOrderItem $it): array {
                return [
                    'id' => $it->id,
                    'item_type' => $it->item_type instanceof \BackedEnum ? $it->item_type->value : (string) $it->item_type,
                    'name' => $it->name,
                    'quantity' => (float) $it->quantity,
                    'unit_price' => (float) $it->unit_price,
                    'total' => (float) $it->total,
                    'service_id' => $it->service_id,
                    'product_id' => $it->product_id,
                ];
            })->values()->all();
        }

        $platformOpsDelegate = $delegatedByPlatform && (bool) $user->is_platform_user && $user->hasPermission('platform.ops.read');
        $workshopSide = $user->role->isWorkshopSide();
        $providerCanExecute = $platformOpsDelegate || $workshopSide;
        $canExecuteNow = $providerCanExecute && ($isActiveOrder || $hasPositivePrepaidBalance);

        return [
            'data' => [
                'lookup' => [
                    'order_number' => ($orderNumber ?? '') !== '' ? $orderNumber : null,
                    'plate_number' => ($plateNumber ?? '') !== '' ? $plateNumber : null,
                ],
                'work_order' => $order ? [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'work_order_number' => $order->work_order_number,
                    'status' => $order->status instanceof WorkOrderStatus ? $order->status->value : (string) $order->status,
                    'is_active' => $isActiveOrder,
                ] : null,
                'vehicle' => $vehicle ? [
                    'id' => $vehicle->id,
                    'plate_number' => $vehicle->plate_number,
                    'make' => $vehicle->make,
                    'model' => $vehicle->model,
                ] : null,
                'show_service_lines' => $showServiceLines,
                'service_lines' => $serviceLines,
                'prepaid' => [
                    'is_prepaid_company' => $isPrepaid,
                    'fleet_main_balance' => $fleetMainBalance,
                    'vehicle_wallet_balance' => $vehicleWalletBalance,
                    'customer_main_balance' => $customerMainBalance,
                    'has_positive_balance' => $hasPositivePrepaidBalance,
                    'has_any_wallet_balance' => $hasAnyWalletCredit,
                ],
                'execution' => [
                    'provider_can_execute_service' => $providerCanExecute,
                    'can_execute_now' => $canExecuteNow,
                ],
                'arrival' => [
                    'lookup_key' => ($orderNumber ?? '') !== '' ? 'order_number' : 'plate_number',
                    'lookup_value' => ($orderNumber ?? '') !== '' ? $orderNumber : (($plateNumber ?? '') !== '' ? $plateNumber : null),
                    'has_active_work_order' => $isActiveOrder,
                    'provider_can_execute_service' => $providerCanExecute,
                    'can_execute_now' => $canExecuteNow,
                    'prepaid_balance_ok' => $hasPositivePrepaidBalance,
                ],
                'delegation' => [
                    'by_platform' => $delegatedByPlatform,
                    'company_id' => $delegatedByPlatform ? $companyId : null,
                    'company_name' => $delegatedByPlatform ? (string) ($tenantCompany->name ?? '') : null,
                ],
            ],
            'trace_id' => app('trace_id'),
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/work-orders",
     *     tags={"WorkOrders"},
     *     summary="Create a work order",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"customer_id","vehicle_id"},
     *
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="vehicle_id", type="integer"),
     *             @OA\Property(property="assigned_technician_id", type="integer"),
     *             @OA\Property(property="priority", type="string", enum={"low","normal","high","urgent"}),
     *             @OA\Property(property="customer_complaint", type="string"),
     *             @OA\Property(property="driver_name", type="string"),
     *             @OA\Property(property="driver_phone", type="string"),
     *             @OA\Property(property="odometer_reading", type="integer"),
     *             @OA\Property(property="mileage_in", type="integer"),
     *             @OA\Property(property="items", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $catalogOnlyPricingActor = $user->role->isFleetSide() || $user->role->isCustomer();
        $effectiveCompanyId = (int) app('tenant_company_id');
        $effectiveBranchId = app()->has('tenant_branch_id') && app('tenant_branch_id') !== null ? (int) app('tenant_branch_id') : null;
        $behavior = $this->behaviorResolver->resolve($effectiveCompanyId, $effectiveBranchId);

        if (($behavior['flags']['require_vehicle_plate'] ?? false) && ! $request->filled('vehicle_plate')) {
            return response()->json([
                'message' => 'vehicle_plate is required for this vertical profile.',
                'trace_id' => app('trace_id'),
                'behavior_applied' => ['require_vehicle_plate'],
            ], 422);
        }

        if ($this->requireBayAssignment($request) && ! $request->filled('bay_id')) {
            return response()->json([
                'message' => 'bay_id is required when bay assignment is enabled.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $companyId = (int) app('tenant_company_id');

        $data = $request->validate([
            'customer_id' => 'required|integer|exists:customers,id',
            'vehicle_id' => 'required|integer|exists:vehicles,id',
            'assigned_technician_id' => 'nullable|integer|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'customer_complaint' => 'nullable|string',
            'driver_name' => 'nullable|string|max:120',
            'driver_phone' => 'nullable|string|max:30',
            'odometer_reading' => 'nullable|integer|min:0',
            'mileage_in' => 'nullable|integer|min:0',
            'vehicle_plate' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:part,labor,service,other',
            'items.*.name' => 'nullable|string',
            'items.*.product_id' => 'nullable|integer',
            'items.*.service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($data['items'] as $idx => $line) {
            $hasService = ! empty($line['service_id']);
            $hasManualPrice = array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '';
            if (! $hasService && ! $hasManualPrice) {
                return response()->json([
                    'message' => 'البند رقم '.($idx + 1).': يجب تحديد service_id للتسعير المركزي أو unit_price للبنود اليدوية.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
            if (! $hasService && empty(trim((string) ($line['name'] ?? '')))) {
                return response()->json([
                    'message' => 'البند رقم '.($idx + 1).': اسم البند مطلوب عند عدم ربط خدمة من الكتالوج.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        }
        if ($catalogOnlyPricingActor) {
            $data['created_by_side'] = 'fleet';
            if (config('portal_rollout.strict_platform_pricing_gate', true)) {
                /** @var PlatformPricingApprovalGateService $approvalGate */
                $approvalGate = app(PlatformPricingApprovalGateService::class);
                if (! $approvalGate->hasApprovedActiveReference($companyId, (int) $data['customer_id'])) {
                    return response()->json([
                        'message' => 'لا يمكن إنشاء أمر العمل قبل اعتماد نسخة أسعار نشطة من إدارة المنصة.',
                        'trace_id' => app('trace_id'),
                    ], 422);
                }
            }
        }

        $resolvedBranchId = $effectiveBranchId ?? ($user->branch_id ? (int) $user->branch_id : null);
        if ($resolvedBranchId === null || $resolvedBranchId < 1) {
            $resolvedBranchId = (int) (Branch::query()
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->value('id') ?? 0);
        }
        if ($resolvedBranchId < 1) {
            return response()->json([
                'message' => 'لا يوجد فرع صالح لربط أمر العمل.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        try {
            $order = $this->workOrderService->create($data, $companyId, $resolvedBranchId, $user->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json([
            'data' => $order->load(['items', 'vehicle', 'customer']),
            'trace_id' => app('trace_id'),
            'behavior_applied' => $this->behaviorResolver->activeBehaviorMarkers($behavior),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/work-orders/{id}",
     *     tags={"WorkOrders"},
     *     summary="Get work order details",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        /** @var User $viewer */
        $viewer = request()->user();
        // Tenant isolation: global scope on WorkOrder (trait HasTenantScope) filters by tenant_company_id from middleware.
        $order = WorkOrder::with([
            'customer', 'vehicle', 'branch',
            'assignedTechnician', 'createdBy',
            'items.product', 'technicians.user',
            'invoice',
        ])->findOrFail($id);
        $order = $this->sanitizeMediaForViewer($order, $viewer);
        $order = $this->sanitizeInvoiceForViewer($order, $viewer);

        return response()->json(['data' => $order, 'trace_id' => app('trace_id')]);
    }

    public function downloadPdf(int $id): Response
    {
        $order = WorkOrder::query()->findOrFail($id);
        $binary = app(WorkOrderPdfService::class)->render($order);
        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $order->order_number) ?: 'work-order';

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$safeName.'.pdf"',
        ]);
    }

    public function shareLinks(int $id): JsonResponse
    {
        $order = WorkOrder::with(['customer', 'vehicle', 'company', 'branch'])->findOrFail($id);
        $pdfService = app(WorkOrderPdfService::class);
        $publicUrl = $pdfService->publicCardUrl($order);
        $issuer = $order->company?->name_ar ?: $order->company?->name ?: '';
        $shareText = "أمر العمل {$order->order_number}\n{$publicUrl}".($issuer !== '' ? "\n— {$issuer}" : '');
        $whatsappOpenHref = 'https://wa.me/?text='.rawurlencode($shareText);

        $driverRaw = trim((string) ($order->driver_phone ?? ''));
        $digits = preg_replace('/\D+/', '', $driverRaw) ?? '';
        $whatsappDriverHref = null;
        if (strlen($digits) >= 8) {
            if (str_starts_with($digits, '0') && strlen($digits) >= 9) {
                $digits = '966'.substr($digits, 1);
            } elseif (! str_starts_with($digits, '966') && strlen($digits) === 9 && ($digits[0] ?? '') === '5') {
                $digits = '966'.$digits;
            }
            $whatsappDriverHref = 'https://wa.me/'.$digits.'?text='.rawurlencode($shareText);
        }

        return response()->json([
            'data' => [
                'public_verify_url' => $publicUrl,
                'whatsapp_open_href' => $whatsappOpenHref,
                'whatsapp_driver_href' => $whatsappDriverHref,
                'share_text' => $shareText,
                'driver_phone' => $order->driver_phone,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    public function shareEmail(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'message' => 'nullable|string|max:2000',
        ]);

        $order = WorkOrder::query()->findOrFail($id);
        $pdfBinary = app(WorkOrderPdfService::class)->render($order);
        $subject = 'أمر عمل '.$order->order_number;
        $bodyText = trim((string) ($data['message'] ?? ''));
        if ($bodyText === '') {
            $bodyText = "مرفق ملف PDF لأمر العمل {$order->order_number}.";
        }

        $safeFile = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $order->order_number) ?: 'work-order';

        try {
            Mail::send([], [], function ($message) use ($data, $subject, $bodyText, $pdfBinary, $safeFile): void {
                $message->to($data['email'])
                    ->subject($subject)
                    ->html(
                        '<div dir="rtl" style="font-family:system-ui,sans-serif;font-size:15px;line-height:1.6">'
                        .nl2br(e($bodyText))
                        .'</div>'
                    )
                    ->attachData($pdfBinary, $safeFile.'.pdf', ['mime' => 'application/pdf']);
            });
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'تعذّر إرسال البريد. تحقق من إعدادات البريد على الخادم.',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        return response()->json([
            'message' => 'تم إرسال البريد مع مرفق PDF.',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function shareWhatsAppDriver(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'message' => 'nullable|string|max:800',
        ]);

        $order = WorkOrder::with('vehicle')->findOrFail($id);
        $publicUrl = app(WorkOrderPdfService::class)->publicCardUrl($order);
        $plate = $order->vehicle?->plate_number ?? '';
        $defaultMsg = 'مرحباً، رابط أمر العمل '.$order->order_number
            .($plate !== '' ? ' — لوحة: '.$plate : '')
            ."\n".$publicUrl;
        $body = trim((string) ($data['message'] ?? '')) !== '' ? trim((string) $data['message']) : $defaultMsg;

        try {
            app(WhatsAppOutboundService::class)->sendManualTextToDriverPhone($order, $body);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'message' => 'تعذّر إرسال الرسالة عبر واتساب.',
                'trace_id' => app('trace_id'),
            ], 503);
        }

        return response()->json([
            'message' => 'تم إرسال الرسالة إلى رقم السائق (عبر مزوّد واتساب المضبوط للشركة).',
            'trace_id' => app('trace_id'),
        ]);
    }

    public function uploadServiceMedia(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->role->isWorkshopSide()) {
            return response()->json([
                'message' => 'رفع صور التنفيذ متاح فقط لمزود الخدمة.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $order = WorkOrder::query()
            ->where('company_id', (int) app('tenant_company_id'))
            ->whereNull('deleted_at')
            ->findOrFail($id);

        $request->validate([
            'before.*' => 'image|max:5120',
            'after.*' => 'image|max:5120',
            'internal.*' => 'image|max:5120',
        ]);

        $before = $order->before_service_images ?? [];
        $after = $order->after_service_images ?? [];
        $internal = $order->internal_service_images ?? [];
        $disk = TenantUploadDisk::name();

        if ($request->hasFile('before')) {
            foreach ($request->file('before') as $file) {
                $path = $file->store("work-orders/{$order->id}/before", $disk);
                $before[] = Storage::disk($disk)->url($path);
            }
        }
        if ($request->hasFile('after')) {
            foreach ($request->file('after') as $file) {
                $path = $file->store("work-orders/{$order->id}/after", $disk);
                $after[] = Storage::disk($disk)->url($path);
            }
        }
        if ($request->hasFile('internal')) {
            foreach ($request->file('internal') as $file) {
                $path = $file->store("work-orders/{$order->id}/internal", $disk);
                $internal[] = Storage::disk($disk)->url($path);
            }
        }

        $order->update([
            'before_service_images' => array_values($before),
            'after_service_images' => array_values($after),
            'internal_service_images' => array_values($internal),
        ]);

        return response()->json([
            'data' => [
                'before_service_images' => $order->before_service_images ?? [],
                'after_service_images' => $order->after_service_images ?? [],
                'internal_service_images' => $order->internal_service_images ?? [],
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function sanitizeMediaForViewer(WorkOrder $order, User $viewer): WorkOrder
    {
        $canSeeInternal = $viewer->role->isWorkshopSide() || (bool) ($viewer->is_platform_user ?? false);
        if (! $canSeeInternal) {
            $order->setAttribute('internal_service_images', []);
        }

        return $order;
    }

    private function sanitizeInvoiceForViewer(WorkOrder $order, User $viewer): WorkOrder
    {
        if ($viewer->role->isWorkshopSide() || (bool) ($viewer->is_platform_user ?? false)) {
            return $order;
        }
        $invoice = $order->invoice;
        if (! $invoice instanceof Invoice) {
            return $order;
        }
        if (! $invoice->isCustomerPortalVisible()) {
            $order->setRelation('invoice', null);
            $order->makeHidden(['invoice_id']);

            return $order;
        }
        if ($viewer->customer_id && (int) $invoice->customer_id !== (int) $viewer->customer_id) {
            $order->setRelation('invoice', null);
            $order->makeHidden(['invoice_id']);
        }

        return $order;
    }

    public function updateExecutionReport(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user->role->isWorkshopSide()) {
            return response()->json([
                'message' => 'تحديث عداد المركبة وتقرير الفني متاح فقط لمزود الخدمة.',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        $order = WorkOrder::query()
            ->where('company_id', (int) app('tenant_company_id'))
            ->whereNull('deleted_at')
            ->findOrFail($id);

        $data = $request->validate([
            'odometer_reading' => 'nullable|integer|min:0',
            'mileage_out' => 'nullable|integer|min:0',
            'technician_notes' => 'nullable|string|max:10000',
            'diagnosis' => 'nullable|string|max:10000',
        ]);

        $order->update($data);

        return response()->json([
            'data' => [
                'id' => $order->id,
                'odometer_reading' => $order->odometer_reading,
                'mileage_out' => $order->mileage_out,
                'technician_notes' => $order->technician_notes,
                'diagnosis' => $order->diagnosis,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/work-orders/{id}",
     *     tags={"WorkOrders"},
     *     summary="Update a work order",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"version"},
     *
     *         @OA\Property(property="version", type="integer"),
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="driver_name", type="string"),
     *         @OA\Property(property="driver_phone", type="string")
     *     )),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) app('tenant_company_id');
        $catalogOnlyPricingActor = $user->role->isFleetSide() || $user->role->isCustomer();

        $data = $request->validate([
            'version' => 'required|integer',
            'assigned_technician_id' => 'nullable|integer|exists:users,id',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'customer_complaint' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'technician_notes' => 'nullable|string',
            'mileage_in' => 'nullable|integer|min:0',
            'mileage_out' => 'nullable|integer|min:0',
            'odometer_reading' => 'nullable|integer|min:0',
            'driver_name' => 'nullable|string|max:120',
            'driver_phone' => 'nullable|string|max:30',
            'notes' => 'nullable|string',
            'items' => 'sometimes|required|array|min:1',
            'items.*.item_type' => 'required|in:part,labor,service,other',
            'items.*.name' => 'nullable|string',
            'items.*.product_id' => 'nullable|integer',
            'items.*.service_id' => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
        ]);

        if (isset($data['items'])) {
            foreach ($data['items'] as $idx => $line) {
                $hasService = ! empty($line['service_id']);
                $hasManualPrice = array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '';
                if (! $hasService && ! $hasManualPrice) {
                    return response()->json([
                        'message' => 'البند رقم '.($idx + 1).': يجب تحديد service_id للتسعير المركزي أو unit_price للبنود اليدوية.',
                        'trace_id' => app('trace_id'),
                    ], 422);
                }
                if (! $hasService && empty(trim((string) ($line['name'] ?? '')))) {
                    return response()->json([
                        'message' => 'البند رقم '.($idx + 1).': اسم البند مطلوب عند عدم ربط خدمة من الكتالوج.',
                        'trace_id' => app('trace_id'),
                    ], 422);
                }
            }
            if ($catalogOnlyPricingActor) {
                $data['created_by_side'] = 'fleet';
            }
        }

        $order = WorkOrder::findOrFail($id);

        if ($catalogOnlyPricingActor && isset($data['items']) && config('portal_rollout.strict_platform_pricing_gate', true)) {
            /** @var PlatformPricingApprovalGateService $approvalGate */
            $approvalGate = app(PlatformPricingApprovalGateService::class);
            if (! $approvalGate->hasApprovedActiveReference($companyId, (int) $order->customer_id)) {
                return response()->json([
                    'message' => 'لا يمكن تعديل بنود أمر العمل قبل اعتماد نسخة أسعار نشطة من إدارة المنصة.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        }

        try {
            $updated = $this->workOrderService->update($order, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 409);
        }

        return response()->json([
            'data' => $updated->load(['items', 'vehicle', 'customer']),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/work-orders/{id}/status",
     *     tags={"WorkOrders"},
     *     summary="Transition work order status",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status","version"},
     *
     *         @OA\Property(property="status", type="string",
     *             enum={"pending","in_progress","on_hold","completed","delivered","cancelled"}),
     *         @OA\Property(property="version", type="integer"),
     *         @OA\Property(property="technician_notes", type="string"),
     *         @OA\Property(property="diagnosis", type="string"),
     *         @OA\Property(property="mileage_out", type="integer")
     *     )),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse"),
     *     @OA\Response(response=409, description="Version conflict"),
     *     @OA\Response(response=422, description="Invalid transition")
     * )
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'status' => 'required|string',
            'version' => 'required|integer',
            'technician_notes' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'mileage_out' => 'nullable|integer|min:0',
            'sensitive_preview_token' => 'nullable|string',
        ]);

        $order = WorkOrder::findOrFail($id);
        $newStatus = WorkOrderStatus::tryFrom($data['status']);

        if (! $newStatus) {
            return response()->json(['message' => "Unknown status: {$data['status']}."], 422);
        }

        if (($user->role->isFleetSide() || $user->role->isCustomer())
            && in_array($newStatus, [WorkOrderStatus::InProgress, WorkOrderStatus::Completed, WorkOrderStatus::Delivered], true)) {
            return response()->json([
                'message' => 'تنفيذ الخدمات متاح فقط لمزود الخدمة (أدوار الورشة).',
                'trace_id' => app('trace_id'),
            ], 403);
        }

        if ($newStatus === WorkOrderStatus::Approved) {
            $token = $data['sensitive_preview_token'] ?? null;
            if ($token === null || trim((string) $token) === '') {
                return response()->json([
                    'message' => 'اعتماد أمر العمل يتطلب المرور بنافذة المراجعة الحساسة وإرسال sensitive_preview_token.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
            try {
                $this->previewTokens->assertValid(
                    $token,
                    (int) app('tenant_company_id'),
                    (int) $user->id,
                    SensitivePreviewTokenService::OP_STATUS_TO_APPROVED,
                    [(int) $order->id],
                    null,
                );
            } catch (\DomainException $e) {
                return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
            }
        }

        $order->version = $data['version'];

        try {
            $updated = $this->workOrderService->transition($order, $newStatus, $data);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'RESOURCE_VERSION_MISMATCH',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        if ($newStatus === WorkOrderStatus::Approved) {
            $this->previewTokens->invalidate($data['sensitive_preview_token'] ?? null);
        }

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/work-orders/{id}",
     *     tags={"WorkOrders"},
     *     summary="Delete a work order (draft or cancelled only)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $order = WorkOrder::findOrFail($id);

        if (! in_array($order->status->value, ['draft', 'pending_manager_approval', 'cancelled'], true)) {
            return response()->json([
                'message' => 'Only draft, pending approval queue, or cancelled work orders can be deleted.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $order->delete();

        return response()->json(['message' => 'Work order deleted.', 'trace_id' => app('trace_id')]);
    }

    /**
     * معاينة سعر بند أمر عمل من الخادم (عقد / سياسة / سعر الخدمة) — لا يُقبل تعديل السعر يدوياً عند ربط service_id.
     */
    public function linePricingPreview(Request $request): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) app('tenant_company_id');

        $data = $request->validate([
            'customer_id' => [
                'required',
                'integer',
                Rule::exists('customers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'vehicle_id' => [
                'required',
                'integer',
                Rule::exists('vehicles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'quantity' => ['nullable', 'numeric', 'min:0.001'],
        ]);

        $vehicle = Vehicle::query()
            ->where('company_id', $companyId)
            ->whereKey((int) $data['vehicle_id'])
            ->firstOrFail();

        if ((int) $vehicle->customer_id !== (int) $data['customer_id']) {
            return response()->json([
                'message' => 'المركبة المحددة لا تنتمي إلى العميل المحدد.',
                'trace_id' => app('trace_id'),
            ], 422);
        }
        $customerId = (int) $data['customer_id'];

        if (config('portal_rollout.strict_platform_pricing_gate', true)) {
            /** @var PlatformPricingApprovalGateService $approvalGate */
            $approvalGate = app(PlatformPricingApprovalGateService::class);
            if (! $approvalGate->hasApprovedActiveReference($companyId, $customerId)) {
                return response()->json([
                    'message' => 'لا يمكن عرض التسعير قبل اعتماد نسخة أسعار نشطة من إدارة المنصة.',
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        }

        $branchId = app()->has('tenant_branch_id') && app('tenant_branch_id') !== null
            ? (int) app('tenant_branch_id')
            : ($user->branch_id ? (int) $user->branch_id : null);
        $qty = isset($data['quantity']) ? (float) $data['quantity'] : 1.0;

        try {
            $resolved = $this->pricingResolver->resolve(
                $companyId,
                (int) $data['customer_id'],
                (int) $data['service_id'],
                $branchId,
                null,
                (int) $data['vehicle_id'],
                false,
                $qty,
            );
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json([
            'data' => [
                'unit_price' => $resolved->unitPrice,
                'tax_rate' => $resolved->taxRate,
                'pricing_source' => $resolved->source->value,
                'pricing_source_label_ar' => $resolved->source->labelAr(),
                'pricing_policy_id' => $resolved->policyId,
                'pricing_contract_service_item_id' => $resolved->contractServiceItemId,
                'resolution_level' => $resolved->resolutionLevel,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    private function requireBayAssignment(Request $request): bool
    {
        $user = $request->user();
        $cid = (int) app('tenant_company_id');
        $bid = app()->has('tenant_branch_id') && app('tenant_branch_id') !== null
            ? (int) app('tenant_branch_id')
            : ($user->branch_id ? (int) $user->branch_id : null);
        $behavior = $this->behaviorResolver->resolve($cid, $bid);

        return (bool) ($behavior['rules']['work_orders.require_bay_assignment'] ?? false);
    }
}
