<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function __construct(private readonly InventoryService $inventoryService) {}

    /**
     * Create a Goods Receipt Note (GRN) for a Purchase Order.
     * Posts stock_movements for each product received.
     * All steps run in a single DB transaction.
     *
     * @param array{
     *   delivery_note_number?: string,
     *   notes?: string,
     *   items: array<array{purchase_item_id: int, received_quantity: float, notes?: string}>
     * } $data
     */
    public function createReceipt(
        Purchase $purchase,
        array    $data,
        int      $userId,
        string   $traceId,
    ): GoodsReceipt {
        return DB::transaction(function () use ($purchase, $data, $userId, $traceId) {
            $this->guardPOReceivable($purchase);

            $counter = GoodsReceipt::where('company_id', $purchase->company_id)->count() + 1;

            $receipt = GoodsReceipt::create([
                'uuid'                  => Str::uuid(),
                'company_id'            => $purchase->company_id,
                'branch_id'             => $purchase->branch_id,
                'created_by_user_id'    => $userId,
                'purchase_id'           => $purchase->id,
                'supplier_id'           => $purchase->supplier_id,
                'grn_number'            => sprintf('GRN-%d-%05d', $purchase->company_id, $counter),
                'status'                => 'posted',
                'delivery_note_number'  => $data['delivery_note_number'] ?? null,
                'notes'                 => $data['notes'] ?? null,
                'trace_id'              => $traceId,
                'received_at'           => now(),
            ]);

            $purchase->load('items');

            foreach ($data['items'] as $lineInput) {
                $poItem = $purchase->items->firstWhere('id', $lineInput['purchase_item_id']);

                if (! $poItem) {
                    throw new \DomainException(
                        "Purchase item #{$lineInput['purchase_item_id']} does not belong to PO #{$purchase->id}."
                    );
                }

                $receivable = (float) $poItem->quantity - (float) $poItem->received_quantity;
                $qty        = min((float) $lineInput['received_quantity'], $receivable);

                if ($qty <= 0) {
                    continue;
                }

                $stockMovementId = null;

                if ($poItem->product_id) {
                    $movement = $this->inventoryService->addStock(
                        companyId:  $purchase->company_id,
                        branchId:   $purchase->branch_id,
                        productId:  $poItem->product_id,
                        quantity:   $qty,
                        userId:     $userId,
                        type:       'purchase_receipt',
                        traceId:    $traceId,
                        note:       "GRN {$receipt->grn_number} — PO {$purchase->reference_number}",
                    );
                    $stockMovementId = $movement->id;
                }

                GoodsReceiptItem::create([
                    'company_id'         => $purchase->company_id,
                    'goods_receipt_id'   => $receipt->id,
                    'purchase_item_id'   => $poItem->id,
                    'product_id'         => $poItem->product_id,
                    'expected_quantity'  => $poItem->quantity,
                    'received_quantity'  => $qty,
                    'unit_cost'          => $poItem->unit_cost,
                    'stock_movement_id'  => $stockMovementId,
                    'notes'              => $lineInput['notes'] ?? null,
                ]);

                $poItem->increment('received_quantity', $qty);
            }

            $purchase->refresh();
            $allDone = $purchase->items->every(fn($i) => (float) $i->received_quantity >= (float) $i->quantity);
            $anyDone = $purchase->items->some(fn($i) => (float) $i->received_quantity > 0);

            $purchase->update([
                'status'      => $allDone ? 'received' : ($anyDone ? 'partial' : $purchase->status->value ?? $purchase->status),
                'received_at' => $allDone ? now() : $purchase->received_at,
            ]);

            Log::info('goods_receipt.posted', [
                'grn'        => $receipt->grn_number,
                'purchase'   => $purchase->reference_number,
                'trace_id'   => $traceId,
                'company_id' => $purchase->company_id,
            ]);

            return $receipt->load('items');
        });
    }

    private function guardPOReceivable(Purchase $purchase): void
    {
        $status = $purchase->status instanceof \BackedEnum
            ? $purchase->status->value
            : $purchase->status;

        $receivable = ['pending', 'ordered', 'partial'];
        if (! in_array($status, $receivable)) {
            throw new \DomainException(
                "Purchase Order '{$purchase->reference_number}' cannot receive goods in status '{$status}'."
            );
        }
    }
}
