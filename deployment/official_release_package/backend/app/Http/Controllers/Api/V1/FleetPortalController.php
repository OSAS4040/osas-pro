<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\ContractServiceItem;
use App\Models\Customer;
use App\Models\Service;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Services\WalletService;
use App\Services\WorkOrderPricingResolverService;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Fleet Portal Controller — واجهة الجهة العميلة
 *
 * هذه الـ Controller خاصة بمستخدمي fleet_contact و fleet_manager فقط.
 * موظفو مركز الخدمة لا يملكون صلاحية الوصول إليها.
 *
 * الأدوار المسموح بها:
 *   fleet_contact  → إنشاء طلبات خدمة + شحن رصيد + عرض
 *   fleet_manager  → كل ما سبق + اعتماد طلبات الائتمان
 */
class FleetPortalController extends Controller
{
    public function __construct(private readonly WalletService $walletService)
    {
    }

    private function companyId(): int
    {
        return (int) app('tenant_company_id');
    }

    private function fleetUser()
    {
        return Auth::user();
    }

    private function assertFleetSide(): void
    {
        $user = $this->fleetUser();
        if (!$user || !$user->role->isFleetSide()) {
            abort(403, 'هذه العملية مخصصة للجهة العميلة فقط.');
        }
    }

    private function assertFleetManager(): void
    {
        $user = $this->fleetUser();
        if (!$user || $user->role !== \App\Enums\UserRole::FleetManager) {
            abort(403, 'اعتماد طلبات الائتمان يتطلب صلاحية Fleet Manager.');
        }
    }

    // ────────────────────────────────────────────────────────────
    // 1. عرض لوحة التحكم — رصيد المحافظ + طلبات الخدمة
    // GET /fleet-portal/dashboard
    // ────────────────────────────────────────────────────────────
    public function dashboard(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        if (!$customerId) {
            return response()->json(['message' => 'حسابك غير مرتبط بجهة عميلة. تواصل مع مدير النظام.'], 422);
        }

        $walletSummary = $this->walletService->getBalanceSummary($companyId, $customerId);

        $recentWorkOrders = WorkOrder::where('company_id', $companyId)
            ->whereHas('vehicle', fn ($q) => $q->where('customer_id', $customerId))
            ->where('created_by_side', 'fleet')
            ->with(['vehicle:id,plate_number,make,model', 'items'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'customer_id'       => $customerId,
                'wallets'           => $walletSummary,
                'recent_orders'     => $recentWorkOrders,
            ],
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // 2. قائمة مركبات الجهة العميلة
    // GET /fleet-portal/vehicles
    // ────────────────────────────────────────────────────────────
    public function vehicles(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $vehicles = Vehicle::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->with('customer:id,name')
            ->paginate(50);

        return response()->json($vehicles);
    }

    /**
     * كتالوج الخدمات النشطة (بدون الاعتماد على سعر يدوي من العميل).
     */
    public function serviceCatalog(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $companyId = $this->companyId();
        $user      = $this->fleetUser();

        $vehicleId = $request->filled('vehicle_id') ? $request->integer('vehicle_id') : null;

        $q = Service::query()
            ->where('company_id', $companyId)
            ->where('is_active', true);

        $customerId = $user->customer_id;
        if ($customerId) {
            $customer = Customer::query()
                ->where('company_id', $companyId)
                ->where('id', $customerId)
                ->first();
            if ($customer && $customer->pricing_contract_id) {
                $contract = Contract::query()
                    ->where('company_id', $companyId)
                    ->where('id', $customer->pricing_contract_id)
                    ->first();
                if ($contract !== null && $this->fleetContractIsEffectiveToday($contract)) {
                    $ids = $this->fleetContractAllowedServiceIds(
                        $companyId,
                        (int) $contract->id,
                        $user->branch_id ? (int) $user->branch_id : null,
                        $vehicleId,
                    );
                    if ($ids === []) {
                        $q->whereRaw('1 = 0');
                    } else {
                        $q->whereIn('id', $ids);
                    }
                }
            }
        }

        $rows = $q->orderBy('name')
            ->get(['id', 'name', 'name_ar', 'code', 'estimated_minutes']);

        return response()->json(['data' => $rows, 'trace_id' => app('trace_id')]);
    }

    /**
     * معاينة السعر المحسوم من الخادم فقط (مصدر التسعير ووضوحه).
     */
    public function previewWorkOrderLinePrice(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        if (! $customerId) {
            return response()->json(['message' => 'حسابك غير مرتبط بجهة عميلة.'], 422);
        }

        $data = $request->validate([
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'vehicle_id' => ['nullable', 'integer'],
        ]);

        $vehicleId = isset($data['vehicle_id']) ? (int) $data['vehicle_id'] : null;
        if ($vehicleId !== null) {
            Vehicle::where('company_id', $companyId)
                ->where('id', $vehicleId)
                ->where('customer_id', $customerId)
                ->firstOrFail();
        }

        try {
            $resolved = app(WorkOrderPricingResolverService::class)->resolve(
                $companyId,
                (int) $customerId,
                (int) $data['service_id'],
                $user->branch_id ? (int) $user->branch_id : null,
                null,
                $vehicleId,
                true,
                1.0,
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
                'note' => 'تم تسعير هذا البند تلقائيًا حسب سياسة التسعير المعتمدة للشركة — لا يُقبل تمرير سعر من الواجهة.',
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // 3. إنشاء طلب خدمة من قِبل الجهة العميلة
    // POST /fleet-portal/work-orders
    // ────────────────────────────────────────────────────────────
    public function createWorkOrder(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $data = $request->validate([
            'vehicle_id' => [
                'required',
                'integer',
            ],
            'service_id' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->where(fn ($q) => $q->where('company_id', $companyId)->where('is_active', true)),
            ],
            'customer_complaint' => 'nullable|string|max:1000',
            'mileage'            => 'nullable|integer|min:0',
            'driver_name'        => 'nullable|string|max:255',
            'driver_phone'       => 'nullable|string|max:30',
            'use_credit'         => 'boolean',
            'notes'              => 'nullable|string',
        ]);

        // تحقق أن المركبة تنتمي للجهة العميلة
        $vehicle = Vehicle::where('company_id', $companyId)
            ->where('id', $data['vehicle_id'])
            ->where('customer_id', $customerId)
            ->firstOrFail();

        $useCredit    = !empty($data['use_credit']);
        $approvalStatus = $useCredit ? 'pending' : 'not_required';

        $workOrderService = app(\App\Services\WorkOrderService::class);

        try {
            $workOrder = $workOrderService->create([
                'customer_id'        => $customerId,
                'vehicle_id'         => $vehicle->id,
                'customer_complaint' => $data['customer_complaint'] ?? null,
                'odometer_reading'   => $data['mileage'] ?? null,
                'driver_name'        => $data['driver_name'] ?? null,
                'driver_phone'       => $data['driver_phone'] ?? null,
                'notes'              => $data['notes'] ?? null,
                'created_by_side'    => 'fleet',
                'approval_status'    => $approvalStatus,
                'items'              => [
                    [
                        'item_type' => 'service',
                        'service_id' => (int) $data['service_id'],
                        'quantity' => 1,
                        'product_id' => null,
                    ],
                ],
            ], $companyId, $user->branch_id, $user->id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $workOrder->update([
            'created_by_side' => 'fleet',
            'approval_status' => $approvalStatus,
        ]);

        return response()->json([
            'data'    => $workOrder->load('vehicle:id,plate_number,make,model'),
            'message' => 'تم إنشاء طلب الخدمة بنجاح.' .
                ($data['use_credit'] ?? false ? ' يرجى انتظار موافقة مدير الأسطول.' : ''),
        ], 201);
    }

    // ────────────────────────────────────────────────────────────
    // 4. اعتماد طلب الائتمان — Fleet Manager فقط
    // POST /fleet-portal/work-orders/{id}/approve-credit
    // ────────────────────────────────────────────────────────────
    public function approveCredit(Request $request, int $id): JsonResponse
    {
        $this->assertFleetManager();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $workOrder = WorkOrder::where('company_id', $companyId)
            ->where('id', $id)
            ->where('created_by_side', 'fleet')
            ->whereHas('vehicle', fn ($q) => $q->where('customer_id', $customerId))
            ->firstOrFail();

        if ($workOrder->approval_status === 'approved' && $workOrder->credit_authorized) {
            return response()->json([
                'data'    => $workOrder,
                'message' => 'هذا الطلب معتمد مسبقاً.',
                'trace_id' => app('trace_id'),
            ]);
        }

        if (! in_array((string) $workOrder->approval_status, ['pending', 'rejected', 'not_required'], true)) {
            return response()->json([
                'message' => "Work order approval transition {$workOrder->approval_status} -> approved is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $workOrder->update([
            'approval_status'          => 'approved',
            'credit_authorized'        => true,
            'fleet_approved_by_user_id'=> $user->id,
            'fleet_approved_at'        => now(),
        ]);

        return response()->json([
            'data'    => $workOrder->fresh(),
            'message' => 'تم اعتماد طلب الائتمان. يمكن للمركبة الآن الدخول.',
            'trace_id' => app('trace_id'),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // 5. رفض / إلغاء طلب الائتمان — Fleet Manager فقط
    // POST /fleet-portal/work-orders/{id}/reject-credit
    // ────────────────────────────────────────────────────────────
    public function rejectCredit(Request $request, int $id): JsonResponse
    {
        $this->assertFleetManager();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $workOrder = WorkOrder::where('company_id', $companyId)
            ->where('id', $id)
            ->where('created_by_side', 'fleet')
            ->whereHas('vehicle', fn ($q) => $q->where('customer_id', $customerId))
            ->firstOrFail();

        if ($workOrder->approval_status === 'rejected') {
            return response()->json([
                'data'    => $workOrder,
                'message' => 'تم رفض طلب الائتمان مسبقاً.',
                'trace_id' => app('trace_id'),
            ]);
        }

        if (! in_array((string) $workOrder->approval_status, ['pending', 'approved'], true)) {
            return response()->json([
                'message' => "Work order approval transition {$workOrder->approval_status} -> rejected is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        $workOrder->update([
            'approval_status'   => 'rejected',
            'credit_authorized' => false,
        ]);

        return response()->json([
            'data'    => $workOrder->fresh(),
            'message' => 'تم رفض طلب الائتمان.',
            'trace_id' => app('trace_id'),
        ]);
    }

    // ────────────────────────────────────────────────────────────
    // 6. شحن رصيد المحفظة — fleet_contact أو fleet_manager
    // POST /fleet-portal/wallet/top-up
    // ────────────────────────────────────────────────────────────
    public function topUp(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        if (!$customerId) {
            return response()->json(['message' => 'حسابك غير مرتبط بجهة عميلة.'], 422);
        }

        $data = $request->validate([
            'amount'          => 'required|numeric|min:1',
            'wallet_type'     => 'nullable|in:fleet_main,vehicle_wallet',
            'vehicle_id'      => 'nullable|integer',
            'idempotency_key' => 'required|string|max:255',
            'notes'           => 'nullable|string',
        ]);

        $walletType = $data['wallet_type'] ?? 'fleet_main';

        if ($walletType === 'fleet_main') {
            $txn = $this->walletService->topUpFleet(
                companyId:      $companyId,
                customerId:     $customerId,
                vehicleId:      null,
                amount:         (float) $data['amount'],
                invoiceId:      null,
                paymentId:      null,
                userId:         $user->id,
                traceId:        $request->attributes->get('trace_id', (string) Str::uuid()),
                idempotencyKey: $data['idempotency_key'],
                branchId:       $user->branch_id,
                notes:          $data['notes'] ?? null,
            );
        } else {
            // شحن محفظة مركبة بعينها — مع التحقق من ملكية المركبة
            $vehicle = Vehicle::where('company_id', $companyId)
                ->where('id', $data['vehicle_id'] ?? 0)
                ->where('customer_id', $customerId)
                ->firstOrFail();

            $txn = $this->walletService->topUpFleet(
                companyId:      $companyId,
                customerId:     $customerId,
                vehicleId:      (int) $vehicle->id,
                amount:         (float) $data['amount'],
                invoiceId:      null,
                paymentId:      null,
                userId:         $user->id,
                traceId:        $request->attributes->get('trace_id', (string) Str::uuid()),
                idempotencyKey: $data['idempotency_key'],
                branchId:       $user->branch_id,
                notes:          $data['notes'] ?? null,
            );
        }

        return response()->json([
            'data'    => $txn,
            'message' => 'تم شحن الرصيد بنجاح.',
        ], 201);
    }

    // ────────────────────────────────────────────────────────────
    // 7. ملخص المحافظ والأرصدة
    // GET /fleet-portal/wallet/summary
    // ────────────────────────────────────────────────────────────
    public function walletSummary(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $summary = $this->walletService->getBalanceSummary($companyId, $customerId ?? 0);

        return response()->json(['data' => $summary]);
    }

    // ────────────────────────────────────────────────────────────
    // 8. سجل المعاملات
    // GET /fleet-portal/wallet/transactions
    // ────────────────────────────────────────────────────────────
    public function transactions(Request $request): JsonResponse
    {
        $this->assertFleetSide();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $txns = \App\Models\WalletTransaction::where('company_id', $companyId)
            ->whereHas('wallet', fn ($q) => $q->where('customer_id', $customerId))
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json($txns);
    }

    // ────────────────────────────────────────────────────────────
    // 9. طلبات الخدمة المعلقة (بانتظار الاعتماد)
    // GET /fleet-portal/work-orders/pending-approval
    // ────────────────────────────────────────────────────────────
    public function pendingApproval(Request $request): JsonResponse
    {
        $this->assertFleetManager();
        $user       = $this->fleetUser();
        $companyId  = $this->companyId();
        $customerId = $user->customer_id;

        $orders = WorkOrder::where('company_id', $companyId)
            ->where('created_by_side', 'fleet')
            ->where('approval_status', 'pending')
            ->whereHas('vehicle', fn ($q) => $q->where('customer_id', $customerId))
            ->with(['vehicle:id,plate_number,make,model', 'items'])
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($orders);
    }

    private function fleetContractIsEffectiveToday(Contract $contract): bool
    {
        if (($contract->status ?? '') !== 'active') {
            return false;
        }
        $today = now()->toDateString();

        return $contract->start_date->format('Y-m-d') <= $today
            && $contract->end_date->format('Y-m-d') >= $today;
    }

    /**
     * @return list<int>
     */
    private function fleetContractAllowedServiceIds(int $companyId, int $contractId, ?int $branchId, ?int $vehicleId): array
    {
        $lines = ContractServiceItem::query()
            ->where('company_id', $companyId)
            ->where('contract_id', $contractId)
            ->where('status', 'active')
            ->where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');
                if ($branchId !== null) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($lines as $line) {
            if ($this->fleetLineMatchesVehicle($line, $vehicleId)) {
                $out[] = (int) $line->service_id;
            }
        }

        return array_values(array_unique($out));
    }

    private function fleetLineMatchesVehicle(ContractServiceItem $line, ?int $vehicleId): bool
    {
        if ($line->applies_to_all_vehicles) {
            return true;
        }
        $ids = array_map('intval', $line->vehicle_ids ?? []);
        if ($vehicleId === null) {
            return $ids === [];
        }

        return in_array($vehicleId, $ids, true);
    }
}
