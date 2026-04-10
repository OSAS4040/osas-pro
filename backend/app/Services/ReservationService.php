<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Intelligence\Events\InventoryReserved;
use App\Intelligence\Events\StockMovementRecorded;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    public function __construct(
        private readonly IntelligentEventEmitter $intelligentEvents,
    ) {}

    public function reserve(
        int     $companyId,
        int     $branchId,
        int     $productId,
        float   $quantity,
        int     $userId,
        string  $referenceType,
        int     $referenceId,
        ?int    $workOrderId = null,
        ?\Carbon\Carbon $expiresAt = null,
        ?string $traceId = null,
    ): InventoryReservation {
        return DB::transaction(function () use (
            $companyId, $branchId, $productId, $quantity,
            $userId, $referenceType, $referenceId,
            $workOrderId, $expiresAt, $traceId
        ) {
            $inventory = Inventory::where('company_id', $companyId)
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                throw new \DomainException("No stock record for product #{$productId} in branch #{$branchId}.");
            }

            $available = (float) $inventory->quantity - (float) $inventory->reserved_quantity;

            if ($available < $quantity) {
                throw new \DomainException(
                    "Insufficient available stock. Available: {$available}, Requested: {$quantity}."
                );
            }

            $inventory->increment('reserved_quantity', $quantity);
            $inventory->increment('version');

            $reservation = InventoryReservation::create([
                'uuid'               => Str::uuid(),
                'company_id'         => $companyId,
                'branch_id'          => $branchId,
                'created_by_user_id' => $userId,
                'product_id'         => $productId,
                'inventory_id'       => $inventory->id,
                'work_order_id'      => $workOrderId,
                'reference_type'     => $referenceType,
                'reference_id'       => $referenceId,
                'quantity'           => $quantity,
                'status'             => ReservationStatus::Pending,
                'expires_at'         => $expiresAt,
            ]);

            $this->intelligentEvents->emit(new InventoryReserved(
                companyId: $companyId,
                branchId: $branchId,
                causedByUserId: $userId,
                reservationId: $reservation->id,
                productId: $productId,
                quantity: (float) $quantity,
                workOrderId: $workOrderId,
                referenceType: $referenceType,
                referenceId: $referenceId,
                sourceContext: 'ReservationService::reserve',
            ));

            return $reservation;
        });
    }

    public function consume(InventoryReservation $reservation, string $traceId): InventoryReservation
    {
        return DB::transaction(function () use ($reservation, $traceId) {
            $reservation->refresh();

            if (! $reservation->canTransitionTo(ReservationStatus::Consumed)) {
                throw new \DomainException(
                    "Cannot consume reservation in status [{$reservation->status->value}]."
                );
            }

            $inventory = Inventory::where('id', $reservation->inventory_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inventory->decrement('quantity', $reservation->quantity);
            $inventory->decrement('reserved_quantity', $reservation->quantity);
            $inventory->increment('version');

            $movement = StockMovement::create([
                'uuid'               => Str::uuid(),
                'company_id'         => $reservation->company_id,
                'branch_id'          => $reservation->branch_id,
                'product_id'         => $reservation->product_id,
                'created_by_user_id' => $reservation->created_by_user_id,
                'type'               => StockMovementType::SaleDeduction->value,
                'quantity'           => -$reservation->quantity,
                'quantity_before'    => (float) $inventory->quantity + $reservation->quantity,
                'quantity_after'     => (float) $inventory->quantity,
                'reference_type'     => $reservation->reference_type,
                'reference_id'       => $reservation->reference_id,
                'trace_id'           => $traceId,
                'note'               => "Reservation #{$reservation->id} consumed.",
                'created_at'         => now(),
            ]);

            $typeStr = $movement->type instanceof \BackedEnum
                ? $movement->type->value
                : (string) $movement->type;
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
                sourceContext: 'ReservationService::consume',
            ));

            $reservation->update(['status' => ReservationStatus::Consumed]);

            return $reservation->fresh();
        });
    }

    public function release(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation->refresh();

            if (! $reservation->canTransitionTo(ReservationStatus::Released)) {
                throw new \DomainException(
                    "Cannot release reservation in status [{$reservation->status->value}]."
                );
            }

            $inventory = Inventory::where('id', $reservation->inventory_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inventory->decrement('reserved_quantity', $reservation->quantity);
            $inventory->increment('version');

            $reservation->update(['status' => ReservationStatus::Released]);

            return $reservation->fresh();
        });
    }

    public function cancel(InventoryReservation $reservation): InventoryReservation
    {
        return DB::transaction(function () use ($reservation) {
            $reservation->refresh();

            if (! $reservation->canTransitionTo(ReservationStatus::Canceled)) {
                throw new \DomainException(
                    "Cannot cancel reservation in status [{$reservation->status->value}]."
                );
            }

            $inventory = Inventory::where('id', $reservation->inventory_id)
                ->lockForUpdate()
                ->firstOrFail();

            $inventory->decrement('reserved_quantity', $reservation->quantity);
            $inventory->increment('version');

            $reservation->update(['status' => ReservationStatus::Canceled]);

            return $reservation->fresh();
        });
    }

    public function expireOverdue(int $companyId): int
    {
        $expired = InventoryReservation::withoutGlobalScope('tenant')
            ->where('company_id', $companyId)
            ->where('status', ReservationStatus::Pending)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->get();

        $count = 0;
        foreach ($expired as $reservation) {
            try {
                DB::transaction(function () use ($reservation) {
                    $inventory = Inventory::where('id', $reservation->inventory_id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $inventory->decrement('reserved_quantity', $reservation->quantity);
                    $inventory->increment('version');

                    $reservation->update(['status' => ReservationStatus::Expired]);
                });
                $count++;
            } catch (\Throwable) {
            }
        }

        return $count;
    }
}
