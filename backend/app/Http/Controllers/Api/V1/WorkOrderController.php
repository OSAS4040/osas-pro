<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\WorkOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="WorkOrders", description="Work order management")
 */
class WorkOrderController extends Controller
{
    public function __construct(private readonly WorkOrderService $workOrderService) {}

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
            'notes'                  => 'nullable|string',
            'items'                  => 'nullable|array',
            'items.*.item_type'      => 'required|in:part,labor,service,other',
            'items.*.name'           => 'required|string',
            'items.*.product_id'     => 'nullable|integer',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'=> 'nullable|numeric|min:0',
        ]);

        $user  = $request->user();
        $order = $this->workOrderService->create($data, $user->company_id, $user->branch_id, $user->id);

        return response()->json([
            'data'     => $order->load(['items', 'vehicle', 'customer']),
            'trace_id' => app('trace_id'),
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
            'items'                  => 'nullable|array',
            'items.*.item_type'      => 'required|in:part,labor,service,other',
            'items.*.name'           => 'required|string',
            'items.*.product_id'     => 'nullable|integer',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
        ]);

        $order = WorkOrder::findOrFail($id);

        try {
            $updated = $this->workOrderService->update($order, $data);
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
            'status'           => 'required|string',
            'version'          => 'required|integer',
            'technician_notes' => 'nullable|string',
            'diagnosis'        => 'nullable|string',
            'mileage_out'      => 'nullable|integer|min:0',
        ]);

        $order     = WorkOrder::findOrFail($id);
        $newStatus = WorkOrderStatus::tryFrom($data['status']);

        if (! $newStatus) {
            return response()->json(['message' => "Unknown status: {$data['status']}."], 422);
        }

        $order->version = $data['version'];

        try {
            $updated = $this->workOrderService->transition($order, $newStatus, $data);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 409);
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

        if (! in_array($order->status->value, ['draft', 'cancelled'])) {
            return response()->json([
                'message'  => 'Only draft or cancelled work orders can be deleted.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $order->delete();

        return response()->json(['message' => 'Work order deleted.', 'trace_id' => app('trace_id')]);
    }
}
