<?php

namespace App\Intelligence\Events;

/**
 * Emitted when a stock_movements row is created from a real inventory operation.
 */
final class StockMovementRecorded extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $stockMovementId,
        private readonly int $productId,
        private readonly string $movementType,
        private readonly float $quantityDelta,
        private readonly ?string $referenceType,
        private readonly ?int $referenceId,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'StockMovementRecorded';
    }

    public function aggregateType(): string
    {
        return 'stock_movement';
    }

    public function aggregateId(): string
    {
        return (string) $this->stockMovementId;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return array_filter([
            'stock_movement_id' => $this->stockMovementId,
            'product_id' => $this->productId,
            'movement_type' => $this->movementType,
            'quantity_delta' => $this->quantityDelta,
            'reference_type' => $this->referenceType,
            'reference_id' => $this->referenceId,
        ], fn ($v) => $v !== null);
    }
}
