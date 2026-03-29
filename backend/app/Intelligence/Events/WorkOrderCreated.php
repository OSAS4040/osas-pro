<?php

namespace App\Intelligence\Events;

final class WorkOrderCreated extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $workOrderId,
        private readonly string $orderNumber,
        private readonly string $status,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'WorkOrderCreated';
    }

    public function aggregateType(): string
    {
        return 'work_order';
    }

    public function aggregateId(): string
    {
        return (string) $this->workOrderId;
    }

    public function payload(): array
    {
        return [
            'work_order_id' => $this->workOrderId,
            'order_number'  => $this->orderNumber,
            'status'        => $this->status,
        ];
    }
}
