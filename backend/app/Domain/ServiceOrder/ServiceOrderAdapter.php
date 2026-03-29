<?php

namespace App\Domain\ServiceOrder;

use App\Models\WorkOrder;

/**
 * Thin wrapper around {@see WorkOrder} for generalized "service order" domain language.
 * Does not replace the model or table; use {@see underlying()} for full Eloquent behavior.
 */
final class ServiceOrderAdapter
{
    public function __construct(private readonly WorkOrder $workOrder) {}

    public static function fromWorkOrder(WorkOrder $workOrder): self
    {
        return new self($workOrder);
    }

    public function underlying(): WorkOrder
    {
        return $this->workOrder;
    }

    public function id(): int
    {
        return (int) $this->workOrder->getKey();
    }
}
