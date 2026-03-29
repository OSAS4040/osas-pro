<?php

namespace App\Services;

use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderService
{
    /**
     * Create a new Purchase Order with items.
     */
    public function createPO(array $data, int $companyId, int $branchId, int $userId, string $traceId): Purchase
    {
        return DB::transaction(function () use ($data, $companyId, $branchId, $userId, $traceId) {
            $subtotal = 0;
            $taxTotal = 0;
            $itemsData = [];

            foreach ($data['items'] as $item) {
                $lineTotal = $item['quantity'] * $item['unit_cost'];
                $taxRate   = $item['tax_rate'] ?? 15;
                $lineTax   = $lineTotal * ($taxRate / 100);
                $subtotal += $lineTotal;
                $taxTotal += $lineTax;
                $itemsData[] = [
                    'company_id'        => $companyId,
                    'product_id'        => $item['product_id'] ?? null,
                    'name'              => $item['name'],
                    'sku'               => $item['sku'] ?? null,
                    'quantity'          => $item['quantity'],
                    'received_quantity' => 0,
                    'unit_cost'         => $item['unit_cost'],
                    'tax_rate'          => $taxRate,
                    'tax_amount'        => $lineTax,
                    'total'             => $lineTotal + $lineTax,
                ];
            }

            $counter = Purchase::where('company_id', $companyId)->withTrashed()->count() + 1;

            $purchase = Purchase::create([
                'uuid'                => Str::uuid(),
                'company_id'          => $companyId,
                'branch_id'           => $branchId,
                'supplier_id'         => $data['supplier_id'],
                'created_by_user_id'  => $userId,
                'reference_number'    => sprintf('PO-%d-%05d', $companyId, $counter),
                'status'              => 'pending',
                'subtotal'            => $subtotal,
                'tax_amount'          => $taxTotal,
                'total'               => $subtotal + $taxTotal,
                'currency'            => 'SAR',
                'notes'               => $data['notes'] ?? null,
                'expected_at'         => $data['expected_at'] ?? null,
                'trace_id'            => $traceId,
            ]);

            foreach ($itemsData as $item) {
                PurchaseItem::create(array_merge($item, ['purchase_id' => $purchase->id]));
            }

            return $purchase;
        });
    }

    /**
     * Transition PO status.
     * Allowed: pending → ordered → (partial|received) → cancelled
     */
    public function transition(Purchase $purchase, string $newStatus): Purchase
    {
        $allowed = [
            'pending'  => ['ordered', 'cancelled'],
            'ordered'  => ['partial', 'received', 'cancelled'],
            'partial'  => ['received', 'cancelled'],
            'received' => [],
            'cancelled' => [],
        ];

        $current = $purchase->status instanceof \BackedEnum
            ? $purchase->status->value
            : $purchase->status;

        if (! in_array($newStatus, $allowed[$current] ?? [])) {
            throw new \DomainException(
                "Invalid PO status transition from '{$current}' to '{$newStatus}'."
            );
        }

        $purchase->update(['status' => $newStatus]);

        return $purchase->fresh();
    }
}
