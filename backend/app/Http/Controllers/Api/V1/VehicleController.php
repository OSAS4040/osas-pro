<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Intelligence\Events\VehicleCreated;
use App\Models\Vehicle;
use App\Services\IntelligentEventEmitter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * @OA\Tag(name="Vehicles", description="Vehicle management")
 */
class VehicleController extends Controller
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/vehicles",
     *     tags={"Vehicles"},
     *     summary="List vehicles",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="customer_id", in="query", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Success")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $vehicles = Vehicle::with(['customer', 'branch'])
            ->when($request->customer_id, fn($q) => $q->where('customer_id', $request->customer_id))
            ->when($request->search, fn($q) => $q->where(function ($q) use ($request) {
                $q->where('plate_number', 'ilike', "%{$request->search}%")
                  ->orWhere('vin', 'ilike', "%{$request->search}%")
                  ->orWhere('make', 'ilike', "%{$request->search}%")
                  ->orWhere('model', 'ilike', "%{$request->search}%");
            }))
            ->when(isset($request->is_active), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $vehicles, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/vehicles",
     *     tags={"Vehicles"},
     *     summary="Create a vehicle",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(required=true,
     *         @OA\JsonContent(
     *             required={"customer_id","plate_number","make","model"},
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="plate_number", type="string"),
     *             @OA\Property(property="make", type="string"),
     *             @OA\Property(property="model", type="string"),
     *             @OA\Property(property="year", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Created")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id'   => 'nullable|integer',
            'plate_number'  => 'required|string|max:20',
            'vin'           => 'nullable|string|max:17',
            'make'          => 'required|string|max:100',
            'model'         => 'required|string|max:100',
            'year'          => 'nullable|integer|min:1900|max:2100',
            'color'         => 'nullable|string|max:50',
            'engine_type'   => 'nullable|string|max:100',
            'fuel_type'     => 'nullable|in:gasoline,diesel,electric,hybrid,other',
            'transmission'  => 'nullable|in:automatic,manual,cvt',
            'mileage_in'    => 'nullable|integer|min:0',
            'notes'         => 'nullable|string',
        ]);

        $user = $request->user();

        $vehicle = Vehicle::create(array_merge($data, [
            'uuid'                => Str::uuid(),
            'company_id'          => $user->company_id,
            'branch_id'           => $user->branch_id,
            'created_by_user_id'  => $user->id,
        ]));

        $this->intelligentEvents->emit(new VehicleCreated(
            companyId: (int) $user->company_id,
            branchId: $user->branch_id ? (int) $user->branch_id : null,
            causedByUserId: (int) $user->id,
            vehicleId: $vehicle->id,
            customerId: $vehicle->customer_id ? (int) $vehicle->customer_id : null,
            plateNumber: (string) $vehicle->plate_number,
            sourceContext: 'api.v1.vehicles.store',
        ));

        return response()->json([
            'data'     => $vehicle->load('customer'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['customer', 'branch', 'workOrders' => fn($q) => $q->latest()->limit(5)])->findOrFail($id);

        return response()->json(['data' => $vehicle, 'trace_id' => app('trace_id')]);
    }

    /**
     * Digital Card data — enriched vehicle info for the digital card view
     */
    public function digitalCard(int $id): JsonResponse
    {
        $vehicle = Vehicle::with(['customer', 'branch'])->findOrFail($id);

        $workOrdersCount = \DB::table('work_orders')
            ->where('vehicle_id', $id)
            ->where('company_id', $vehicle->company_id)
            ->count();

        $recentWorkOrders = \DB::table('work_orders')
            ->where('vehicle_id', $id)
            ->where('company_id', $vehicle->company_id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'order_number', 'status', 'customer_complaint as description', 'created_at']);

        // Wallet balance
        $walletBalance = 0;
        $transactions = [];
        try {
            $wallet = \DB::table('wallets')
                ->where('walletable_type', 'App\\Models\\Vehicle')
                ->where('walletable_id', $id)
                ->first();
            if ($wallet) {
                $walletBalance = (float) $wallet->balance;
                $transactions = \DB::table('wallet_transactions')
                    ->where('wallet_id', $wallet->id)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get();
            }
        } catch (\Throwable $e) {
            // wallet tables may not exist
        }

        // Loyalty points
        $loyaltyPoints = 0;
        $pointsRedeemed = 0;
        try {
            $loyalty = \DB::table('loyalty_points')
                ->where('customer_id', $vehicle->customer_id)
                ->where('company_id', $vehicle->company_id)
                ->first();
            if ($loyalty) {
                $loyaltyPoints = (int) ($loyalty->balance ?? 0);
                $pointsRedeemed = (int) ($loyalty->redeemed ?? 0);
            }
        } catch (\Throwable $e) {}

        // Total spent
        $totalSpent = \DB::table('invoices')
            ->where('company_id', $vehicle->company_id)
            ->where('customer_id', $vehicle->customer_id)
            ->sum('total') ?? 0;

        $vehicleData = $vehicle->toArray();
        $vehicleData['work_orders_count'] = $workOrdersCount;
        $vehicleData['wallet_balance'] = $walletBalance;
        $vehicleData['loyalty_points'] = $loyaltyPoints;
        $vehicleData['points_redeemed'] = $pointsRedeemed;
        $vehicleData['total_spent'] = (float) $totalSpent;
        $vehicleData['tracking_id'] = $vehicle->tracking_id ?? null;
        $vehicleData['tracking_url'] = $vehicle->tracking_url ?? null;
        $vehicleData['dashcam_id'] = $vehicle->dashcam_id ?? null;
        $vehicleData['dashcam_url'] = $vehicle->dashcam_url ?? null;

        return response()->json([
            'data' => $vehicleData,
            'work_orders' => $recentWorkOrders,
            'transactions' => $transactions,
            'trace_id' => app('trace_id'),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $vehicle = Vehicle::findOrFail($id);

        $data = $request->validate([
            'plate_number'  => 'sometimes|string|max:20',
            'vin'           => 'nullable|string|max:17',
            'make'          => 'sometimes|string|max:100',
            'model'         => 'sometimes|string|max:100',
            'year'          => 'nullable|integer|min:1900|max:2100',
            'color'         => 'nullable|string|max:50',
            'fuel_type'     => 'nullable|in:gasoline,diesel,electric,hybrid,other',
            'transmission'  => 'nullable|in:automatic,manual,cvt',
            'is_active'     => 'nullable|boolean',
            'notes'         => 'nullable|string',
        ]);

        $vehicle->update($data);
        $vehicle->increment('version');

        return response()->json(['data' => $vehicle, 'trace_id' => app('trace_id')]);
    }

    public function destroy(int $id): JsonResponse
    {
        Vehicle::findOrFail($id)->delete();

        return response()->json(['message' => 'Vehicle deleted.', 'trace_id' => app('trace_id')]);
    }
}
