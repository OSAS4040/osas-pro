<?php

namespace App\Intelligence\Events;

/**
 * Emitted when inventory is reserved (pending reservation row created).
 */
final class InventoryReserved extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $reservationId,
        private readonly int $productId,
        private readonly float $quantity,
        private readonly ?int $workOrderId,
        private readonly string $referenceType,
        private readonly int $referenceId,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'InventoryReserved';
    }

    public function aggregateType(): string
    {
        return 'inventory_reservation';
    }

    public function aggregateId(): string
    {
        return (string) $this->reservationId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return array_filter([
            'reservation_id'  => $this->reservationId,
            'product_id'      => $this->productId,
            'quantity'        => $this->quantity,
            'work_order_id'   => $this->workOrderId,
            'reference_type'  => $this->referenceType,
            'reference_id'    => $this->referenceId,
        ], fn ($v) => $v !== null);
    }
}
