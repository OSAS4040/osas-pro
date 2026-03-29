<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GoodsReceipt;
use App\Models\Purchase;
use App\Services\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(name="GoodsReceipts", description="Goods receipt management")
 */
class GoodsReceiptController extends Controller
{
    public function __construct(private readonly GoodsReceiptService $receiptService) {}

    public function index(Request $request): JsonResponse
    {
        $receipts = GoodsReceipt::with(['supplier', 'purchase', 'branch'])
            ->when($request->purchase_id, fn($q) => $q->where('purchase_id', $request->purchase_id))
            ->when($request->supplier_id, fn($q) => $q->where('supplier_id', $request->supplier_id))
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 25));

        return response()->json(['data' => $receipts, 'trace_id' => app('trace_id')]);
    }

    public function store(Request $request, int $purchaseId): JsonResponse
    {
        $data = $request->validate([
            'delivery_note_number'          => 'nullable|string|max:100',
            'notes'                         => 'nullable|string',
            'items'                         => 'required|array|min:1',
            'items.*.purchase_item_id'      => 'required|integer',
            'items.*.received_quantity'     => 'required|numeric|min:0.0001',
            'items.*.notes'                 => 'nullable|string',
        ]);

        $user     = $request->user();
        $purchase = Purchase::where('company_id', $user->company_id)
            ->with('items')
            ->findOrFail($purchaseId);

        $receipt = $this->receiptService->createReceipt(
            purchase: $purchase,
            data:     $data,
            userId:   $user->id,
            traceId:  app('trace_id'),
        );

        return response()->json([
            'data'     => $receipt->load('items.product', 'supplier', 'purchase'),
            'trace_id' => app('trace_id'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $receipt = GoodsReceipt::with(['items.product', 'items.purchaseItem', 'supplier', 'purchase', 'branch', 'createdBy'])
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        return response()->json(['data' => $receipt, 'trace_id' => app('trace_id')]);
    }

    public function byPurchase(Request $request, int $purchaseId): JsonResponse
    {
        $user     = $request->user();
        $purchase = Purchase::where('company_id', $user->company_id)->findOrFail($purchaseId);

        $receipts = GoodsReceipt::with('items.product')
            ->where('purchase_id', $purchase->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $receipts, 'trace_id' => app('trace_id')]);
    }
}
