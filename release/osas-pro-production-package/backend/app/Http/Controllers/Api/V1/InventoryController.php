<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustInventoryRequest;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\StockMovement;
use App\Services\Config\VerticalBehaviorResolverService;
use App\Services\InventoryService;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Inventory", description="Inventory management")
 */
class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService   $inventoryService,
        private readonly ReservationService $reservationService,
        private readonly VerticalBehaviorResolverService $behaviorResolver,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/inventory",
     *     tags={"Inventory"},
     *     summary="List inventory levels",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="low_stock", in="query", @OA\Schema(type="boolean")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $inventory = Inventory::with(['product.unit', 'branch'])
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->boolean('low_stock'), fn($q) => $q->whereColumn('quantity', '<=', 'reorder_point'))
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $inventory, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventory/{id}",
     *     tags={"Inventory"},
     *     summary="Get inventory record",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function show(int $id): JsonResponse
    {
        $inventory = Inventory::with(['product.unit', 'branch'])->findOrFail($id);

        return response()->json(['data' => $inventory, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/inventory/adjust",
     *     tags={"Inventory"},
     *     summary="Adjust inventory (add / subtract / set)",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_id","product_id","quantity","type"},
     *             @OA\Property(property="branch_id", type="integer"),
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="quantity", type="number"),
     *             @OA\Property(property="type", type="string", enum={"add","subtract","set"}),
     *             @OA\Property(property="unit_id", type="integer"),
     *             @OA\Property(property="unit_cost", type="number"),
     *             @OA\Property(property="note", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function adjust(AdjustInventoryRequest $request): JsonResponse
    {
        $data    = $request->validated();
        $user    = $request->user();
        $traceId = app('trace_id');
        $behavior = $this->behaviorResolver->resolve((int) $user->company_id, $user->branch_id ? (int) $user->branch_id : null);
        $allowNegativeStock = (bool) ($behavior['flags']['allow_negative_stock'] ?? false);

        $movement = match($data['type']) {
            'subtract' => $this->inventoryService->deductStock(
                companyId:     $user->company_id,
                branchId:      $data['branch_id'],
                productId:     $data['product_id'],
                quantity:      $data['quantity'],
                userId:        $user->id,
                referenceType: 'manual_adjustment',
                referenceId:   0,
                traceId:       $traceId,
                unitId:        $data['unit_id'] ?? null,
                unitCost:      $data['unit_cost'] ?? null,
                note:          $data['note'] ?? null,
                allowNegativeStock: $allowNegativeStock,
            ),
            'set' => $this->handleSetAdjustment($data, $user, $traceId),
            default => $this->inventoryService->addStock(
                companyId:     $user->company_id,
                branchId:      $data['branch_id'],
                productId:     $data['product_id'],
                quantity:      $data['quantity'],
                userId:        $user->id,
                type:          'manual_add',
                traceId:       $traceId,
                unitId:        $data['unit_id'] ?? null,
                unitCost:      $data['unit_cost'] ?? null,
                note:          $data['note'] ?? null,
            ),
        };

        return response()->json([
            'data'             => $movement,
            'trace_id'         => $traceId,
            'behavior_applied' => $this->behaviorResolver->activeBehaviorMarkers($behavior),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventory/movements",
     *     tags={"Inventory"},
     *     summary="List stock movements",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="branch_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="type", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function movements(Request $request): JsonResponse
    {
        $movements = StockMovement::with(['product', 'unit', 'createdBy'])
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->when($request->type, fn($q) => $q->where('type', $request->type))
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $movements, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/inventory/reservations",
     *     tags={"Inventory"},
     *     summary="List inventory reservations",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="product_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/PaginatedResponse")
     * )
     */
    public function reservations(Request $request): JsonResponse
    {
        $reservations = InventoryReservation::with(['product', 'workOrder', 'createdBy'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->product_id, fn($q) => $q->where('product_id', $request->product_id))
            ->when($request->branch_id, fn($q) => $q->where('branch_id', $request->branch_id))
            ->orderByDesc('id')
            ->paginate($request->per_page ?? 25);

        return response()->json(['data' => $reservations, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/inventory/reservations",
     *     tags={"Inventory"},
     *     summary="Create an inventory reservation",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"branch_id","product_id","quantity","reference_type","reference_id"},
     *             @OA\Property(property="branch_id", type="integer"),
     *             @OA\Property(property="product_id", type="integer"),
     *             @OA\Property(property="quantity", type="number"),
     *             @OA\Property(property="reference_type", type="string"),
     *             @OA\Property(property="reference_id", type="integer"),
     *             @OA\Property(property="work_order_id", type="integer"),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function createReservation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id'      => ['required', 'integer', 'exists:branches,id'],
            'product_id'     => ['required', 'integer', 'exists:products,id'],
            'quantity'       => ['required', 'numeric', 'min:0.0001'],
            'reference_type' => ['required', 'string'],
            'reference_id'   => ['required', 'integer'],
            'work_order_id'  => ['nullable', 'integer', 'exists:work_orders,id'],
            'expires_at'     => ['nullable', 'date', 'after:now'],
        ]);
        $behavior = $this->behaviorResolver->resolve((int) $request->user()->company_id, $request->user()->branch_id ? (int) $request->user()->branch_id : null);
        if (($behavior['flags']['track_expiry'] ?? false) && empty($data['expires_at'])) {
            return response()->json([
                'message' => 'expires_at is required when expiry tracking is enabled.',
                'trace_id' => app('trace_id'),
                'behavior_applied' => ['track_expiry'],
            ], 422);
        }

        $reservation = $this->reservationService->reserve(
            companyId:     $request->user()->company_id,
            branchId:      $data['branch_id'],
            productId:     $data['product_id'],
            quantity:      $data['quantity'],
            userId:        $request->user()->id,
            referenceType: $data['reference_type'],
            referenceId:   $data['reference_id'],
            workOrderId:   $data['work_order_id'] ?? null,
            expiresAt:     isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null,
            traceId:       app('trace_id'),
        );

        return response()->json(['data' => $reservation, 'trace_id' => app('trace_id')], 201);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/inventory/reservations/{id}/consume",
     *     tags={"Inventory"},
     *     summary="Consume a reservation (deducts actual stock)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function consumeReservation(int $id): JsonResponse
    {
        $reservation = InventoryReservation::findOrFail($id);
        $result      = $this->reservationService->consume($reservation, app('trace_id'));

        return response()->json(['data' => $result, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/inventory/reservations/{id}/release",
     *     tags={"Inventory"},
     *     summary="Release a reservation (frees reserved quantity)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function releaseReservation(int $id): JsonResponse
    {
        $reservation = InventoryReservation::findOrFail($id);
        $result      = $this->reservationService->release($reservation);

        return response()->json(['data' => $result, 'trace_id' => app('trace_id')]);
    }

    /**
     * @OA\Patch(
     *     path="/api/v1/inventory/reservations/{id}/cancel",
     *     tags={"Inventory"},
     *     summary="Cancel a reservation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, ref="#/components/schemas/ApiResponse")
     * )
     */
    public function cancelReservation(int $id): JsonResponse
    {
        $reservation = InventoryReservation::findOrFail($id);
        $current     = (string) $reservation->status->value;
        $allowedFrom = ['pending'];

        if (! in_array($current, $allowedFrom, true)) {
            return response()->json([
                'message'  => "Reservation status transition {$current} -> cancel is not allowed.",
                'code'     => 'TRANSITION_NOT_ALLOWED',
                'status'   => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        try {
            $result = $this->reservationService->cancel($reservation);
        } catch (\DomainException $e) {
            return response()->json([
                'message'  => $e->getMessage(),
                'code'     => 'TRANSITION_NOT_ALLOWED',
                'status'   => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

        return response()->json(['data' => $result, 'trace_id' => app('trace_id')]);
    }

    private function handleSetAdjustment(array $data, $user, string $traceId): StockMovement
    {
        $stock = $this->inventoryService->getStockLevel(
            $user->company_id, $data['branch_id'], $data['product_id']
        );

        $currentQty = $stock['quantity'];
        $diff       = $data['quantity'] - $currentQty;

        if (abs($diff) < 0.0001) {
            throw new \DomainException("Stock is already at {$currentQty}. No adjustment needed.");
        }

        return $diff > 0
            ? $this->inventoryService->addStock(
                companyId:  $user->company_id,
                branchId:   $data['branch_id'],
                productId:  $data['product_id'],
                quantity:   $diff,
                userId:     $user->id,
                type:       'set_adjustment',
                traceId:    $traceId,
                unitId:     $data['unit_id'] ?? null,
                unitCost:   $data['unit_cost'] ?? null,
                note:       $data['note'] ?? 'Set quantity adjustment',
            )
            : $this->inventoryService->deductStock(
                companyId:     $user->company_id,
                branchId:      $data['branch_id'],
                productId:     $data['product_id'],
                quantity:      abs($diff),
                userId:        $user->id,
                referenceType: 'set_adjustment',
                referenceId:   0,
                traceId:       $traceId,
                unitId:        $data['unit_id'] ?? null,
                note:          $data['note'] ?? 'Set quantity adjustment',
            );
    }
}
