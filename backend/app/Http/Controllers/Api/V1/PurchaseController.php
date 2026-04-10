<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Services\BillingModelPolicyService;
use App\Services\InventoryService;
use App\Services\PurchaseOrderService;
use App\Support\Media\TenantUploadDisk;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(name="Purchases", description="Purchase order management")
 */
class PurchaseController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $poService,
        private readonly BillingModelPolicyService $billingModelPolicy,
    ) {}

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
        $user = $request->user();

        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $user->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $data = $request->validate([
            'supplier_id'        => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')->where(fn($q) => $q->where('company_id', $user->company_id)),
            ],
            'notes'              => 'nullable|string',
            'expected_at'        => 'nullable|date',
            'items'              => 'required|array|min:1',
            'items.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where(fn($q) => $q->where('company_id', $user->company_id)),
            ],
            'items.*.name'       => 'required|string',
            'items.*.sku'        => 'nullable|string',
            'items.*.quantity'   => 'required|numeric|min:0.001',
            'items.*.unit_cost'  => 'required|numeric|min:0',
            'items.*.tax_rate'   => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $purchase = $this->poService->createPO(
                data:      $data,
                companyId: $user->company_id,
                branchId:  $user->branch_id,
                userId:    $user->id,
                traceId:   app('trace_id'),
            );
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Unable to create purchase order with provided data.',
                'trace_id' => app('trace_id'),
            ], 422);
        }

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
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $request->user()->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $data = $request->validate([
            'status' => 'required|in:ordered,partial,received,cancelled',
        ]);

        $purchase = Purchase::where('company_id', $request->user()->company_id)->findOrFail($id);
        $updated  = $this->poService->transition($purchase, $data['status']);

        return response()->json(['data' => $updated, 'trace_id' => app('trace_id')]);
    }

    public function receive(Request $request, int $id): JsonResponse
    {
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $request->user()->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $data = $request->validate([
            'items'                    => 'required|array',
            'items.*.purchase_item_id' => 'required|integer',
            'items.*.received_qty'     => 'required|numeric|min:0',
        ]);

        $purchase = Purchase::with('items')
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
        if (! in_array((string) $purchase->status->value, ['ordered', 'partial'], true)) {
            return response()->json([
                'message' => "Purchase status transition {$purchase->status->value} -> receive is not allowed.",
                'code' => 'TRANSITION_NOT_ALLOWED',
                'status' => 409,
                'trace_id' => app('trace_id'),
            ], 409);
        }

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

    /** رفع PDF أو مستند مرتبط بأمر الشراء */
    public function uploadDocument(Request $request, int $id): JsonResponse
    {
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $request->user()->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $request->validate([
            'file' => 'required|file|mimes:pdf|max:10240',
        ]);

        $purchase = Purchase::where('company_id', $request->user()->company_id)->findOrFail($id);
        $file       = $request->file('file');
        $origName   = $file->getClientOriginalName();
        $safe       = Str::slug(pathinfo($origName, PATHINFO_FILENAME)) . '-' . Str::random(6) . '.pdf';
        $disk = TenantUploadDisk::name();
        $path       = $file->storeAs(
            "purchase_docs/{$purchase->company_id}/{$purchase->id}",
            $safe,
            $disk
        );

        $attachments   = $purchase->document_attachments ?? [];
        $attachments[] = [
            'name'        => $origName,
            'path'        => $path,
            'url'         => Storage::disk($disk)->url($path),
            'uploaded_at' => now()->toIso8601String(),
        ];
        $purchase->update(['document_attachments' => $attachments]);

        return response()->json([
            'data'    => $purchase->fresh()->load(['items', 'supplier']),
            'message' => 'تم رفع الملف.',
        ]);
    }

    public function deleteDocument(Request $request, int $id, int $index): JsonResponse
    {
        try {
            $this->billingModelPolicy->assertTenantMayOperate((int) $request->user()->company_id);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage(), 'trace_id' => app('trace_id')], 422);
        }

        $purchase    = Purchase::where('company_id', $request->user()->company_id)->findOrFail($id);
        $attachments = $purchase->document_attachments ?? [];
        $idx         = $index;
        if (! isset($attachments[$idx])) {
            return response()->json(['message' => 'المرفق غير موجود'], 404);
        }
        $path = $attachments[$idx]['path'] ?? null;
        $disk = TenantUploadDisk::name();
        if ($path && Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
        array_splice($attachments, $idx, 1);
        $purchase->update(['document_attachments' => $attachments]);

        return response()->json(['data' => $purchase->fresh()]);
    }
}
