<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="Purchases", description="Purchase order management")
 */
class PurchaseController extends Controller
{
    public function __construct(private readonly PurchaseOrderService $poService) {}

    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::with(['supplier', 'branch'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $purchases, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'        => 'required|integer',
            'notes'              => 'nullable|string',
            'expected_at'        => 'nullable|date',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer',
            'items.*.name'       => 'required|string',
            'items.*.sku'        => 'nullable|string',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0|max:100',
        ]);

        $user     = $request->user();
        $purchase = $this->poService->createPO(
            data:      $data,
            companyId: $user->company_id,
            branchId:  $user->branch_id,
            userId:    $user->id,
            traceId:   app('trace_id'),
        );

        return response()->json([
            'data'     => $purchase->load('items', 'supplier'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $purchase = Purchase::with(['items.product', 'supplier', 'branch'])->findOrFail($id);

        return response()->json(['data' => $purchase, 'trace_id' => app('trace_id')]);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:ordered,partial,received,cancelled',
        ]);

        $purchase = Purchase::where('company_id', $request->user()->company_id)->findOrFail($id);
        $updated  = $this->poService->transition($purchase, $data['status']);

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items'                    => 'required|array',
            'items.*.purchase_item_id' => 'required|integer',
            'items.*.received_qty'     => 'required|numeric|min:0',
        ]);

        $purchase = Purchase::with('items')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $user    = $request->user();
        $traceId = app('trace_id');

        foreach ($data['items'] as $receipt) {
            $item = $purchase->items->firstWhere('id', $receipt['purchase_item_id']);
            if (! $item || ! $item->product_id) {
                continue;
            }

            $remaining = (float) $item->quantity - (float) $item->received_quantity;
            $qty       = min((float) $receipt['received_qty'], $remaining);
            if ($qty <= 0) {
                continue;
            }

            $item->increment('received_quantity', $qty);

            app(InventoryService::class)->addStock(
                companyId: $purchase->company_id,
                branchId:  $purchase->branch_id,
                productId: $item->product_id,
                quantity:  $qty,
                userId:    $user->id,
                type:      'purchase_receipt',
                traceId:   $traceId,
                note:      "Purchase {$purchase->reference_number}",
            );
        }

        $purchase->refresh();
        $allReceived = $purchase->items->every(fn($i) => (float) $i->received_quantity >= (float) $i->quantity);
        $anyReceived = $purchase->items->some(fn($i) => (float) $i->received_quantity > 0);

        $purchase->update([
            'status'      => $allReceived ? 'received' : ($anyReceived ? 'partial' : $purchase->status->value ?? $purchase->status),
            'received_at' => $allReceived ? now() : $purchase->received_at,
        ]);

        return response()->json(['data' => $purchase->load('items'), 'trace_id' => $traceId]);
    }
}
