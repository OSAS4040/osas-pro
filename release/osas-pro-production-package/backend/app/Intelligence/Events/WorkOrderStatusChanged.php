<?php

namespace App\Intelligence\Events;

final class WorkOrderStatusChanged extends AbstractDomainEvent
{
    public function __construct(
        ?int $companyId,
        ?int $branchId,
        ?int $causedByUserId,
        private readonly int $workOrderId,
        private readonly string $fromStatus,
        private readonly string $toStatus,
        ?string $sourceContext = null,
    ) {
        parent::__construct($companyId, $branchId, $causedByUserId, $sourceContext);
    }

    public function name(): string
    {
        return 'WorkOrderStatusChanged';
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
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
        ];
    }
}
