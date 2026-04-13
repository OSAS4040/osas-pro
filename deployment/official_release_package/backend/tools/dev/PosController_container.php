<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\POSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="POS", description="Point-of-sale fast-track B2C flow")
 */
class POSController extends Controller
{
    public function __construct(private readonly POSService $posService) {}

    /**
     * @OA\Post(
     *     path="/api/v1/pos/sale",
     *     tags={"POS"},
     *     summary="Execute a POS fast-track sale (atomic: invoice + payment + stock deduction)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="Idempotency-Key",
     *         in="header", required=true,
     *         description="Unique key per transaction (UUID recommended)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"items","payment"},
     *             @OA\Property(property="customer_id", type="integer"),
     *             @OA\Property(property="vehicle_id", type="integer"),
     *             @OA\Property(property="customer_type", type="string", enum={"b2c","b2b"}, default="b2c"),
     *             @OA\Property(property="discount_amount", type="number", default=0),
     *             @OA\Property(property="notes", type="string"),
     *             @OA\Property(property="items", type="array", @OA\Items(
     *                 required={"name","quantity","unit_price"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="product_id", type="integer"),
     *                 @OA\Property(property="service_id", type="integer"),
     *                 @OA\Property(property="quantity", type="number"),
     *                 @OA\Property(property="unit_price", type="number"),
     *                 @OA\Property(property="cost_price", type="number"),
     *                 @OA\Property(property="tax_rate", type="number", default=15),
     *                 @OA\Property(property="discount_amount", type="number", default=0)
     *             )),
     *             @OA\Property(property="payment", type="object",
     *                 required={"method","amount"},
     *                 @OA\Property(property="method", type="string", enum={"cash","card","wallet","bank_transfer"}),
     *                 @OA\Property(property="amount", type="number"),
     *                 @OA\Property(property="reference", type="string")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=201, ref="#/components/schemas/ApiResponse"),
     *     @OA\Response(response=409, description="Idempotency payload mismatch"),
     *     @OA\Response(response=422, description="Insufficient stock or validation error")
     * )
     */
    public function sale(Request $request): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json([
                'message'  => 'Idempotency-Key header is required for financial transactions.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $data = $request->validate([
            'customer_id'            => 'nullable|integer|exists:customers,id',
            'vehicle_id'             => 'nullable|integer|exists:vehicles,id',
            'customer_type'          => 'nullable|in:b2c,b2b',
            'discount_amount'        => 'nullable|numeric|min:0',
            'notes'                  => 'nullable|string',
            'items'                  => 'required|array|min:1',
            'items.*.name'           => 'required|string',
            'items.*.product_id'     => 'nullable|integer|exists:products,id',
            'items.*.service_id'     => 'nullable|integer|exists:services,id',
            'items.*.quantity'       => 'required|numeric|min:0.001',
            'items.*.unit_price'     => 'required|numeric|min:0',
            'items.*.cost_price'     => 'nullable|numeric|min:0',
            'items.*.tax_rate'       => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount'=> 'nullable|numeric|min:0',
            'payment'                => 'required|array',
            'payment.method'         => 'required|in:cash,card,wallet,bank_transfer',
            'payment.amount'         => 'required|numeric|min:0',
            'payment.reference'      => 'nullable|string',
        ]);

        $data['idempotency_key'] = $idempotencyKey;
        $user = $request->user();

        try {
            $invoice = $this->posService->sale(
                data:           $data,
                companyId:      $user->company_id,
                branchId:       $user->branch_id,
                userId:         $user->id,
                idempotencyKey: $idempotencyKey,
            );
        } catch (\DomainException $e) {
            $status = str_contains($e->getMessage(), 'Idempotency') ? 409 : 422;
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], $status);
        }

        return response()->json([
            'data'     => $invoice,
            'trace_id' => app('trace_id'),
        ], 201);
    }
}
