<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BillingModelPolicyService;
use App\Services\Config\VerticalBehaviorResolverService;
use App\Services\POSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(name="POS", description="Point-of-sale fast-track B2C flow")
 */
class POSController extends Controller
{
    public function __construct(
        private readonly POSService $posService,
        private readonly VerticalBehaviorResolverService $behaviorResolver,
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

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
        $t0 = microtime(true);
        $seg = [
            'validate_ms' => 0.0,
            'policy_ms' => 0.0,
            'sale_service_ms' => 0.0,
            'response_serialize_ms' => 0.0,
        ];

        Log::info('POS_SALE_ENTERED', [
            'request_id' => $request->header('X-Request-Id'),
            'trace_id' => $request->header('X-Trace-Id'),
            'idempotency_key' => $request->header('Idempotency-Key'),
            'endpoint' => $request->path(),
        ]);

        $user = $request->user();
        $behavior = $this->behaviorResolver->resolve((int) $user->company_id, $user->branch_id ? (int) $user->branch_id : null);

        if (! ($behavior['features']['pos.quick_sale'] ?? true)) {
            return response()->json(['message' => 'POS quick sale is disabled by configuration.', 'trace_id' => app('trace_id')], 403);
        }

        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey) {
            return response()->json([
                'message'  => 'Idempotency-Key header is required for financial transactions.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

        $tv = microtime(true);
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
        $seg['validate_ms'] = round((microtime(true) - $tv) * 1000, 2);

        $data['idempotency_key'] = $idempotencyKey;
        if (($behavior['flags']['require_customer'] ?? false) && ! $request->filled('customer_id')) {
            return response()->json([
                'message' => 'customer_id is required for this vertical profile.',
                'trace_id' => app('trace_id'),
                'behavior_applied' => ['require_customer'],
            ], 422);
        }

        if (($behavior['flags']['enable_cash_only_mode'] ?? false) && $request->input('payment.method') !== 'cash') {
            return response()->json([
                'message' => 'Only cash payment method is allowed for this vertical profile.',
                'trace_id' => app('trace_id'),
                'behavior_applied' => ['enable_cash_only_mode'],
            ], 422);
        }

        $tp = microtime(true);
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $user->company_id);
            if (($data['payment']['method'] ?? '') === 'wallet') {
                $this->billingModelPolicy->assertPrepaidWalletTopUp((int) $user->company_id);
            }
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }
        $seg['policy_ms'] = round((microtime(true) - $tp) * 1000, 2);

        $ts = microtime(true);
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
        } catch (\Throwable $e) {
            Log::error('POS_FAILURE', [
                'trace_id'         => app()->bound('trace_id') ? (string) app('trace_id') : null,
                'request_id'       => app()->bound('request_id') ? (string) app('request_id') : null,
                'idempotency_key'  => $idempotencyKey,
                'company_id'       => $user->company_id,
                'endpoint'         => $request->path(),
                'exception_class'  => $e::class,
                'exception'        => $e->getMessage(),
            ]);
            throw $e;
        }
        $seg['sale_service_ms'] = round((microtime(true) - $ts) * 1000, 2);

        $tr = microtime(true);
        Log::info('POS_SALE_COMPLETED', [
            'request_id' => app()->bound('request_id') ? (string) app('request_id') : $request->header('X-Request-Id'),
            'trace_id' => app()->bound('trace_id') ? (string) app('trace_id') : $request->header('X-Trace-Id'),
            'idempotency_key' => $idempotencyKey,
            'invoice_id' => $invoice->id ?? null,
            'endpoint' => $request->path(),
        ]);
        $seg['response_serialize_ms'] = round((microtime(true) - $tr) * 1000, 2);
        Log::info('POS_TIMING_BREAKDOWN', [
            'request_id' => app()->bound('request_id') ? (string) app('request_id') : null,
            'trace_id' => app()->bound('trace_id') ? (string) app('trace_id') : null,
            'idempotency_key' => $idempotencyKey,
            'company_id' => $user->company_id,
            'invoice_id' => $invoice->id ?? null,
            'total_ms' => round((microtime(true) - $t0) * 1000, 2),
        ] + $seg);

        return response()->json([
            'data'             => $invoice,
            'trace_id'         => app('trace_id'),
            'behavior_applied' => $this->behaviorResolver->activeBehaviorMarkers($behavior),
        ], 201);
    }
}
