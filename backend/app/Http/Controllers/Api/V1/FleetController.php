<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\Vehicle;
use App\Models\WorkOrder;
use App\Enums\WalletType;
use App\Enums\WorkOrderStatus;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FleetController extends Controller
{
    public function __construct(private readonly WalletService $walletService) {}

    private function companyId(): int
    {
        return (int) app('tenant_company_id');
    }

    /**
     * POST /fleet/verify-plate
     * Cashier scans/enters plate number to check eligibility for service.
     */
    public function verifyPlate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plate_number' => 'required|string|max:20',
        ]);

        $companyId = $this->companyId();
        $plate     = strtoupper(trim($data['plate_number']));

        $vehicle = Vehicle::where('company_id', $companyId)
            ->where('plate_number', $plate)
            ->with('customer')
            ->first();

        if (! $vehicle) {
            return response()->json([
                'vehicle'    => null,
                'work_order' => null,
                'wallet'     => null,
                'verdict'    => [
                    'can_proceed'    => false,
                    'payment_mode'   => null,
                    'denial_reason'  => 'vehicle_not_found',
                    'denial_message' => 'لا توجد مركبة مسجلة بهذه اللوحة.',
                ],
            ]);
        }

        $workOrder = WorkOrder::where('company_id', $companyId)
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [WorkOrderStatus::Pending->value, WorkOrderStatus::InProgress->value])
            ->latest()
            ->first();

        if (! $workOrder) {
            return response()->json([
                'vehicle'    => $this->vehicleData($vehicle),
                'work_order' => null,
                'wallet'     => $this->walletData($companyId, $vehicle),
                'verdict'    => [
                    'can_proceed'    => false,
                    'payment_mode'   => null,
                    'denial_reason'  => 'no_active_work_order',
                    'denial_message' => 'لا يوجد أمر عمل نشط لهذه المركبة. يجب إنشاء أمر عمل أولاً.',
                ],
            ]);
        }

        if ($workOrder->approval_status !== 'approved') {
            return response()->json([
                'vehicle'    => $this->vehicleData($vehicle),
                'work_order' => $this->workOrderData($workOrder),
                'wallet'     => $this->walletData($companyId, $vehicle),
                'verdict'    => [
                    'can_proceed'    => false,
                    'payment_mode'   => null,
                    'denial_reason'  => 'work_order_not_approved',
                    'denial_message' => 'أمر العمل غير معتمد بعد. يجب اعتماده من المسؤول.',
                ],
            ]);
        }

        $wallet        = $this->getVehicleWallet($companyId, $vehicle);
        $balance       = $wallet ? (float) $wallet->balance : 0.0;
        $isCredit      = $workOrder->credit_authorized;

        if ($balance > 0) {
            $verdict = ['can_proceed' => true, 'payment_mode' => 'prepaid', 'denial_reason' => null, 'denial_message' => null];
        } elseif ($isCredit) {
            $verdict = ['can_proceed' => true, 'payment_mode' => 'credit', 'denial_reason' => null, 'denial_message' => null];
        } else {
            $verdict = [
                'can_proceed'    => false,
                'payment_mode'   => null,
                'denial_reason'  => 'insufficient_balance',
                'denial_message' => 'رصيد المحفظة غير كافٍ وأمر العمل لا يتضمن تفويض ائتمان.',
            ];
        }

        return response()->json([
            'vehicle'    => $this->vehicleData($vehicle),
            'work_order' => $this->workOrderData($workOrder),
            'wallet'     => [
                'id'       => $wallet?->id,
                'balance'  => $balance,
                'currency' => $wallet?->currency ?? 'SAR',
                'status'   => $wallet?->status ?? 'not_created',
            ],
            'verdict' => $verdict,
        ]);
    }

    /**
     * GET /fleet/customers
     * List fleet customers with their wallet balances.
     */
    public function fleetCustomers(Request $request): JsonResponse
    {
        $companyId = $this->companyId();

        $wallets = CustomerWallet::where('company_id', $companyId)
            ->where('wallet_type', WalletType::FleetMain->value)
            ->with(['customer', 'company'])
            ->get();

        $data = $wallets->map(function ($wallet) use ($companyId) {
            $vehicleWallets = CustomerWallet::where('company_id', $companyId)
                ->where('customer_id', $wallet->customer_id)
                ->where('wallet_type', WalletType::VehicleWallet->value)
                ->with('vehicle')
                ->get();

            return [
                'customer'        => [
                    'id'    => $wallet->customer?->id,
                    'name'  => $wallet->customer?->name,
                    'phone' => $wallet->customer?->phone,
                ],
                'fleet_wallet'    => [
                    'id'       => $wallet->id,
                    'balance'  => (float) $wallet->balance,
                    'currency' => $wallet->currency,
                    'status'   => $wallet->status,
                ],
                'vehicle_wallets' => $vehicleWallets->map(fn($vw) => [
                    'wallet_id'    => $vw->id,
                    'vehicle_id'   => $vw->vehicle_id,
                    'plate'        => $vw->vehicle?->plate_number,
                    'balance'      => (float) $vw->balance,
                    'currency'     => $vw->currency,
                    'status'       => $vw->status,
                ]),
                'total_balance'   => (float) $wallet->balance + $vehicleWallets->sum('balance'),
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * POST /fleet/work-orders/{id}/approve
     * Approve a work order for fleet credit service.
     */
    public function approveWorkOrder(Request $request, int $id): JsonResponse
    {
        if (! $request->user()?->hasPermission('work_orders.update')) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $data = $request->validate([
            'credit_authorized' => 'boolean',
        ]);

        $companyId = $this->companyId();

        $workOrder = WorkOrder::where('company_id', $companyId)
            ->where('id', $id)
            ->whereNotIn('status', [WorkOrderStatus::Cancelled->value, WorkOrderStatus::Delivered->value])
            ->firstOrFail();

        $workOrder->update([
            'approval_status'    => 'approved',
            'approved_by_user_id'=> Auth::id(),
            'approved_at'        => now(),
            'credit_authorized'  => $data['credit_authorized'] ?? false,
        ]);

        return response()->json([
            'message'    => 'Work order approved successfully.',
            'work_order' => [
                'id'                => $workOrder->id,
                'approval_status'   => $workOrder->approval_status,
                'credit_authorized' => $workOrder->credit_authorized,
                'approved_at'       => $workOrder->approved_at,
            ],
            'trace_id' => app('trace_id'),
        ]);
    }

    // -------------------------------------------------------------------------
    private function vehicleData(Vehicle $vehicle): array
    {
        return [
            'id'           => $vehicle->id,
            'plate_number' => $vehicle->plate_number,
            'make'         => $vehicle->make,
            'model'        => $vehicle->model,
            'year'         => $vehicle->year,
            'customer_id'  => $vehicle->customer_id,
            'customer_name'=> $vehicle->customer?->name,
        ];
    }

    private function workOrderData(WorkOrder $wo): array
    {
        return [
            'id'                => $wo->id,
            'order_number'      => $wo->order_number ?? $wo->work_order_number,
            'status'            => $wo->status instanceof \BackedEnum ? $wo->status->value : $wo->status,
            'approval_status'   => $wo->approval_status,
            'credit_authorized' => (bool) $wo->credit_authorized,
            'approved_at'       => $wo->approved_at,
        ];
    }

    private function walletData(int $companyId, Vehicle $vehicle): array
    {
        $wallet = $this->getVehicleWallet($companyId, $vehicle);
        return [
            'id'       => $wallet?->id,
            'balance'  => $wallet ? (float) $wallet->balance : 0.0,
            'currency' => $wallet?->currency ?? 'SAR',
            'status'   => $wallet?->status ?? 'not_created',
        ];
    }

    private function getVehicleWallet(int $companyId, Vehicle $vehicle): ?CustomerWallet
    {
        return CustomerWallet::where('company_id', $companyId)
            ->where('vehicle_id', $vehicle->id)
            ->where('wallet_type', WalletType::VehicleWallet->value)
            ->first();
    }
}
