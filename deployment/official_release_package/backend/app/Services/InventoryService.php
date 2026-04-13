<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Intelligence\Events\StockMovementRecorded;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    public function deductStock(
        int    $companyId,
        int    $branchId,
        int    $productId,
        float  $quantity,
        int    $userId,
        string $referenceType,
        int    $referenceId,
        string $traceId,
        ?int   $unitId = null,
        ?float $unitCost = null,
        ?string $note = null,
        bool $allowNegativeStock = false,
    ): StockMovement {
        return DB::transaction(function () use (
            $companyId, $branchId, $productId, $quantity,
            $userId, $referenceType, $referenceId, $traceId,
            $unitId, $unitCost, $note, $allowNegativeStock
        ) {
            $inventory = Inventory::firstOrCreate(
                ['company_id' => $companyId, 'branch_id' => $branchId, 'product_id' => $productId],
                ['quantity' => 0, 'reserved_quantity' => 0, 'reorder_point' => 0]
            );
            $inventory = Inventory::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->firstOrFail();

            $baseQty = $this->toBaseQuantity($quantity, $unitId, $productId);

            if (! $allowNegativeStock && $inventory->available_quantity < $baseQty) {
                throw new \DomainException(
                    "Insufficient stock for product #{$productId}. " .
                    "Available: {$inventory->available_quantity}, Requested: {$baseQty}."
                );
            }

            $before = (float) $inventory->quantity;
            $after  = $before - $baseQty;

            $inventory->decrement('quantity', $baseQty);
            $inventory->increment('version');

            $movement = StockMovement::create([
                'uuid'               => Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'product_id'         => $productId,
                'created_by_user_id' => $userId,
                'unit_id'            => $unitId,
                'type'               => StockMovementType::SaleDeduction->value,
                'quantity'           => -$baseQty,
                'unit_cost'          => $unitCost,
                'quantity_before'    => $before,
                'quantity_after'     => $after,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'trace_id'           => $traceId,
                'note'               => $note,
                'created_at'         => now(),
            ]);
            DB::afterCommit(function () use ($movement): void {
                $this->emitStockMovement($movement, 'InventoryService::deductStock');
            });

            return $movement;
        });
    }

    public function addStock(
        int    $companyId,
        int    $branchId,
        int    $productId,
        float  $quantity,
        int    $userId,
        string $type,
        string $traceId,
        ?int   $unitId = null,
        ?float $unitCost = null,
        ?string $note = null,
        ?string $referenceType = null,
        ?int   $referenceId = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $companyId, $branchId, $productId, $quantity,
            $userId, $type, $traceId, $unitId, $unitCost,
            $note, $referenceType, $referenceId
        ) {
            $baseQty = $this->toBaseQuantity($quantity, $unitId, $productId);

            $inventory = Inventory::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $inventory = Inventory::create([
                    'company_id'        => $companyId,
                    'branch_id'         => $branchId,
                    'product_id'        => $productId,
                    'quantity'          => 0,
                    'reserved_quantity' => 0,
                    'version'           => 0,
                ]);
            }

            $before = (float) $inventory->quantity;
            $after  = $before + $baseQty;

            $inventory->increment('quantity', $baseQty);
            $inventory->increment('version');

            $movement = StockMovement::create([
                'uuid'               => Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'product_id'         => $productId,
                'created_by_user_id' => $userId,
                'unit_id'            => $unitId,
                'type'               => $type,
                'quantity'           => $baseQty,
                'unit_cost'          => $unitCost,
                'quantity_before'    => $before,
                'quantity_after'     => $after,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'trace_id'           => $traceId,
                'note'               => $note,
                'created_at'         => now(),
            ]);
            DB::afterCommit(function () use ($movement): void {
                $this->emitStockMovement($movement, 'InventoryService::addStock');
            });

            return $movement;
        });
    }

    public function reverseMovement(StockMovement $original, int $userId, string $traceId): StockMovement
    {
        return DB::transaction(function () use ($original, $userId, $traceId) {
            if ($original->reversal_movement_id) {
                throw new \DomainException("Movement #{$original->id} has already been reversed.");
            }

            $isDeduction = (float) $original->quantity < 0;
            $reverseQty  = abs((float) $original->quantity);

            $reversal = $isDeduction
                ? $this->addStock(
                    companyId:     $original->company_id,
                    branchId:      $original->branch_id,
                    productId:     $original->product_id,
                    quantity:      $reverseQty,
                    userId:        $userId,
                    type:          StockMovementType::Reversal->value,
                    traceId:       $traceId,
                    unitId:        $original->unit_id,
                    note:          "Reversal of movement #{$original->id}",
                    referenceType: 'stock_movement',
                    referenceId:   $original->id,
                )
                : $this->deductStock(
                    companyId:     $original->company_id,
                    branchId:      $original->branch_id,
                    productId:     $original->product_id,
                    quantity:      $reverseQty,
                    userId:        $userId,
                    referenceType: 'stock_movement',
                    referenceId:   $original->id,
                    traceId:       $traceId,
                    unitId:        $original->unit_id,
                    note:          "Reversal of movement #{$original->id}",
                );

            DB::table('stock_movements')
                ->where('id', $original->id)
                ->update(['reversal_movement_id' => $reversal->id]);

            DB::table('stock_movements')
                ->where('id', $reversal->id)
                ->update(['original_movement_id' => $original->id]);

            return $reversal->fresh();
        });
    }

    /**
     * Convenience wrapper that returns a single available quantity float.
     * Used by tests and external services that only need the number.
     */
    public function getBalance(int $companyId, int $branchId, int $productId): float
    {
        return $this->getStockLevel($companyId, $branchId, $productId)['available'];
    }

    public function getStockLevel(int $companyId, int $branchId, int $productId): array
    {
        $inventory = Inventory::where('company_id', $companyId)
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->first();

        if (! $inventory) {
            return ['quantity' => 0, 'reserved' => 0, 'available' => 0];
        }

        return [
            'quantity'  => (float) $inventory->quantity,
            'reserved'  => (float) $inventory->reserved_quantity,
            'available' => $inventory->available_quantity,
        ];
    }

    private function toBaseQuantity(float $quantity, ?int $unitId, int $productId): float
    {
        if (! $unitId) {
            return $quantity;
        }

        $product = \App\Models\Product::find($productId);

        if (! $product || ! $product->unit_id || $product->unit_id === $unitId) {
            return $quantity;
        }

        $fromUnit = Unit::find($unitId);
        $toUnit   = Unit::find($product->unit_id);

        if (! $fromUnit || ! $toUnit) {
            return $quantity;
        }

        return $fromUnit->convertTo($toUnit, $quantity);
    }

    private function emitStockMovement(StockMovement $movement, string $sourceContext): void
    {
        $type = $movement->type;
        $typeStr = $type instanceof \BackedEnum ? (string) $type->value : (string) $type;

        $this->intelligentEvents->emit(new StockMovementRecorded(
            companyId: (int) $movement->company_id,
            branchId: $movement->branch_id ? (int) $movement->branch_id : null,
            causedByUserId: $movement->created_by_user_id ? (int) $movement->created_by_user_id : null,
            stockMovementId: (int) $movement->id,
            productId: (int) $movement->product_id,
            movementType: $typeStr,
            quantityDelta: (float) $movement->quantity,
            referenceType: $movement->reference_type,
            referenceId: $movement->reference_id !== null ? (int) $movement->reference_id : null,
            sourceContext: $sourceContext,
        ));
    }
}
