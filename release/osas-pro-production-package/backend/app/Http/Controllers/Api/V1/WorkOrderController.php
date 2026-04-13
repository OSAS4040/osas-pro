<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\Config\VerticalBehaviorResolverService;
use App\Services\Messaging\WhatsAppOutboundService;
use App\Services\SensitivePreviewTokenService;
use App\Services\WorkOrderPdfService;
use App\Services\WorkOrderPricingResolverService;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
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
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="customer_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="vehicle_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $orders = WorkOrder::with(['customer', 'vehicle', 'assignedTechnician', 'branch'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->vehicle_id, fn($q) => $q->where('vehicle_id', $request->vehicle_id))
            ->when($request->technician_id, fn($q) => $q->where('assigned_technician_id', $request->technician_id))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $orders, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/work-orders",
     *     tags={"WorkOrders"},
     *     summary="Create a work order",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"customer_id","vehicle_id"},
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
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $behavior = $this->behaviorResolver->resolve((int) $user->company_id, $user->branch_id ? (int) $user->branch_id : null);

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

        $companyId = (int) $user->company_id;

        $data = $request->validate([
            'customer_id'            => 'required|integer|exists:customers,id',
            'vehicle_id'             => 'required|integer|exists:vehicles,id',
            'assigned_technician_id' => 'nullable|integer|exists:users,id',
            'priority'               => 'nullable|in:low,normal,high,urgent',
            'customer_complaint'     => 'nullable|string',
            'driver_name'            => 'nullable|string|max:120',
            'driver_phone'           => 'nullable|string|max:30',
            'odometer_reading'       => 'nullable|integer|min:0',
            'mileage_in'             => 'nullable|integer|min:0',
            'vehicle_plate'          => 'nullable|string|max:20',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.item_type'      => 'required|in:part,labor,service,other',
            'items.*.name'           => 'nullable|string',
            'items.*.product_id'     => 'nullable|integer',
            'items.*.service_id'     => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'nullable|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'=> 'nullable|numeric|min:0',
        ]);

        foreach ($data['items'] as $idx => $line) {
            $hasService = ! empty($line['service_id']);
            $hasManualPrice = array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '';
            if (! $hasService && ! $hasManualPrice) {
                return response()->json([
                    'message' => "البند رقم ".($idx + 1).": يجب تحديد service_id للتسعير المركزي أو unit_price للبنود اليدوية.",
                    'trace_id' => app('trace_id'),
                ], 422);
            }
            if (! $hasService && empty(trim((string) ($line['name'] ?? '')))) {
                return response()->json([
                    'message' => "البند رقم ".($idx + 1).": اسم البند مطلوب عند عدم ربط خدمة من الكتالوج.",
                    'trace_id' => app('trace_id'),
                ], 422);
            }
        }

        try {
            $order = $this->workOrderService->create($data, $user->company_id, $user->branch_id, $user->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        return response()->json([
            'data'               => $order->load(['items', 'vehicle', 'customer']),
            'trace_id'           => app('trace_id'),
            'behavior_applied'   => $this->behaviorResolver->activeBehaviorMarkers($behavior),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/work-orders/{id}",
     *     tags={"WorkOrders"},
     *     summary="Get work order details",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $order = WorkOrder::with([
            'customer', 'vehicle', 'branch',
            'assignedTechnician', 'createdBy',
            'items.product', 'technicians.user',
            'invoice',
        ])->findOrFail($id);

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

    /**
     * @OA\Put(
     *     path="/api/v1/work-orders/{id}",
     *     tags={"WorkOrders"},
     *     summary="Update a work order",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"version"},
     *         @OA\Property(property="version", type="integer"),
     *         @OA\Property(property="notes", type="string"),
     *         @OA\Property(property="driver_name", type="string"),
     *         @OA\Property(property="driver_phone", type="string")
     *     )),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $companyId = (int) $user->company_id;

        $data = $request->validate([
            'version'                => 'required|integer',
            'assigned_technician_id' => 'nullable|integer|exists:users,id',
            'priority'               => 'nullable|in:low,normal,high,urgent',
            'customer_complaint'     => 'nullable|string',
            'diagnosis'              => 'nullable|string',
            'technician_notes'       => 'nullable|string',
            'mileage_in'             => 'nullable|integer|min:0',
            'mileage_out'            => 'nullable|integer|min:0',
            'odometer_reading'       => 'nullable|integer|min:0',
            'driver_name'            => 'nullable|string|max:120',
            'driver_phone'           => 'nullable|string|max:30',
            'notes'                  => 'nullable|string',
            'items'                  => 'sometimes|required|array|min:1',
            'items.*.item_type'      => 'required|in:part,labor,service,other',
            'items.*.name'           => 'nullable|string',
            'items.*.product_id'     => 'nullable|integer',
            'items.*.service_id'     => [
                'nullable',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'nullable|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        if (isset($data['items'])) {
            foreach ($data['items'] as $idx => $line) {
                $hasService = ! empty($line['service_id']);
                $hasManualPrice = array_key_exists('unit_price', $line) && $line['unit_price'] !== null && $line['unit_price'] !== '';
                if (! $hasService && ! $hasManualPrice) {
                    return response()->json([
                        'message' => "البند رقم ".($idx + 1).": يجب تحديد service_id للتسعير المركزي أو unit_price للبنود اليدوية.",
                        'trace_id' => app('trace_id'),
                    ], 422);
                }
                if (! $hasService && empty(trim((string) ($line['name'] ?? '')))) {
                    return response()->json([
                        'message' => "البند رقم ".($idx + 1).": اسم البند مطلوب عند عدم ربط خدمة من الكتالوج.",
                        'trace_id' => app('trace_id'),
                    ], 422);
                }
            }
        }

        $order = WorkOrder::findOrFail($id);

        try {
            $updated = $this->workOrderService->update($order, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 409);
        }

        return response()->json([
            'data'     => $updated->load(['items', 'vehicle', 'customer']),
            'trace_id' => app('trace_id'),
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/work-orders/{id}/status",
     *     tags={"WorkOrders"},
     *     summary="Transition work order status",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *         required={"status","version"},
     *         @OA\Property(property="status", type="string",
     *             enum={"pending","in_progress","on_hold","completed","delivered","cancelled"}),
     *         @OA\Property(property="version", type="integer"),
     *         @OA\Property(property="technician_notes", type="string"),
     *         @OA\Property(property="diagnosis", type="string"),
     *         @OA\Property(property="mileage_out", type="integer")
     *     )),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse"),
     *     @OA\Response(response=409, description="Version conflict"),
     *     @OA\Response(response=422, description="Invalid transition")
     * )
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
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
                    (int) $request->user()->company_id,
                    (int) $request->user()->id,
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
                'message'  => $e->getMessage(),
                'code'     => 'TRANSITION_NOT_ALLOWED',
                'status'   => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message'  => $e->getMessage(),
                'code'     => 'RESOURCE_VERSION_MISMATCH',
                'status'   => 409,
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
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function destroy(int $id): JsonResponse
    {
        $order = WorkOrder::findOrFail($id);

        if (! in_array($order->status->value, ['draft', 'pending_manager_approval', 'cancelled'], true)) {
            return response()->json([
                'message'  => 'Only draft, pending approval queue, or cancelled work orders can be deleted.',
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
        $companyId = (int) $user->company_id;

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

        $branchId = $user->branch_id ? (int) $user->branch_id : null;
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
        $behavior = $this->behaviorResolver->resolve((int) $user->company_id, $user->branch_id ? (int) $user->branch_id : null);

        return (bool) ($behavior['rules']['work_orders.require_bay_assignment'] ?? false);
    }
}
